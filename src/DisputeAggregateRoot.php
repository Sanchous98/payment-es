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

    private AggregateRootId $paymentIntentId;

    private DisputeStatusEnum $status;

    private Money $money;

    private Money $fee;

    private string $reason;

    public static function create(CreateDisputeCommandInterface $command): static
    {
        if (!$command->getPaymentIntent()->is(PaymentIntentStatusEnum::SUCCEEDED)) {
            throw DisputeException::cannotDisputeOnNotSucceededPayment();
        }

        if ($command->getPaymentIntent()->getMoney()->lessThan($command->getMoney())) {
            throw InvalidAmountException::notGreaterThanCaptured($command->getMoney()->getAmount());
        }

        $self = new static($command->getId());
        $self->recordThat(new DisputeCreated(
            $command->getPaymentIntent()->aggregateRootId(),
            $command->getMoney(),
            $command->getFee(),
            $command->getReason(),
        ));

        return $self;
    }

    public function is(DisputeStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public function getMoney(): Money
    {
        return $this->money;
    }

    public function getFee(): Money
    {
        return $this->fee;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getPaymentIntentId(): AggregateRootId
    {
        return $this->paymentIntentId;
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

    public function __sleep()
    {
        unset($this->eventRecorder);
        return array_keys((array)$this);
    }
}