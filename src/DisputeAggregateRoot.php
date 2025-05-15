<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\Commands\CreateDisputeCommandInterface;
use PaymentSystem\Enum\DisputeStatusEnum;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Events\DisputeCreated;
use PaymentSystem\Events\DisputeLost;
use PaymentSystem\Events\DisputeWon;
use PaymentSystem\Exceptions\DisputeException;
use PaymentSystem\Exceptions\InvalidAmountException;

class DisputeAggregateRoot implements AggregateRoot
{
    use AggregateRootBehaviour;

    private(set) AggregateRootId $paymentIntentId;

    private(set) DisputeStatusEnum $status;

    private(set) Money $money;

    private(set) Money $fee;

    private(set) string $reason;

    public static function create(CreateDisputeCommandInterface $command): static
    {
        if (!$command->paymentIntent->is(PaymentIntentStatusEnum::SUCCEEDED)) {
            throw DisputeException::cannotDisputeOnNotSucceededPayment();
        }

        if ($command->paymentIntent->money->lessThan($command->money)) {
            throw InvalidAmountException::notGreaterThanCaptured($command->money->getAmount());
        }

        $self = new static($command->id);
        $self->recordThat(new DisputeCreated(
            $command->paymentIntent->aggregateRootId(),
            $command->money,
            $command->fee,
            $command->reason,
        ));

        return $self;
    }

    public function is(DisputeStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public function win(): static
    {
        $this->status !== DisputeStatusEnum::CREATED || throw new DisputeException();

        $this->recordThat(new DisputeWon());

        return $this;
    }

    public function loose(): static
    {
        $this->status !== DisputeStatusEnum::CREATED || throw new DisputeException();

        $this->recordThat(new DisputeLost());

        return $this;
    }

    protected function apply(object $event): void
    {
        switch ($event::class) {
            case DisputeCreated::class:
                $this->applyDisputeCreated($event);
                return;
            case DisputeLost::class:
                $this->applyDisputeLost();
                return;
            case DisputeWon::class:
                $this->applyDisputeWon();
                return;
        }
    }

    protected function applyDisputeCreated(DisputeCreated $event): void
    {
        $this->status = DisputeStatusEnum::CREATED;
        $this->money = $event->money;
        $this->fee = $event->fee;
        $this->reason = $event->reason;
        $this->paymentIntentId = $event->paymentIntentId;
    }

    protected function applyDisputeLost(): void
    {
        $this->status = DisputeStatusEnum::LOST;
    }

    protected function applyDisputeWon(): void
    {
        $this->status = DisputeStatusEnum::WON;
    }
}