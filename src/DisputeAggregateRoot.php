<?php

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use Money\Currency;
use Money\Money;
use PaymentSystem\Commands\CreateDisputeCommandInterface;
use PaymentSystem\Enum\DisputeStatusEnum;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Events\DisputeCreated;
use PaymentSystem\Events\DisputeLost;
use PaymentSystem\Events\DisputeWon;
use PaymentSystem\Exceptions\DisputeException;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\ValueObjects\GenericId;

class DisputeAggregateRoot implements AggregateRootWithSnapshotting
{
    use SnapshotBehaviour;
    use AggregateRootBehaviour;

    private AggregateRootId $paymentIntentId;

    private DisputeStatusEnum $status;

    private Money $money;

    private Money $fee;

    private string $reason;

    public function getPaymentIntentId(): AggregateRootId
    {
        return $this->paymentIntentId;
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

    public static function create(CreateDisputeCommandInterface $command): static
    {
        $command->getPaymentIntent()->is(PaymentIntentStatusEnum::SUCCEEDED) || throw DisputeException::cannotDisputeOnNotSucceededPayment();
        $command->getPaymentIntent()->getMoney()->lessThan($command->getMoney()) && throw InvalidAmountException::notGreaterThanCaptured($command->getMoney()->getAmount());

        $self = new static($command->getId());
        $self->recordThat(
            new DisputeCreated(
                $command->getPaymentIntent()->aggregateRootId(),
                $command->getMoney(),
                $command->getFee(),
                $command->getReason(),
            )
        );

        return $self;
    }

    public function win(): static
    {
        if ($this->status !== DisputeStatusEnum::CREATED) {
            throw new DisputeException();
        }

        $this->recordThat(new DisputeWon());

        return $this;
    }

    public function loose(): static
    {
        if ($this->status !== DisputeStatusEnum::CREATED) {
            throw new DisputeException();
        }

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

    protected function apply(DisputeCreated|DisputeLost|DisputeWon $event): void
    {
        switch ($event::class) {
            case DisputeCreated::class:
                $this->applyDisputeCreated($event);
                break;
            case DisputeLost::class:
                $this->applyDisputeLost();
                break;
            case DisputeWon::class:
                $this->applyDisputeWon();
                break;
        }

        ++$this->aggregateRootVersion;
    }

    protected function applySnapshot(Snapshot $snapshot): void
    {
        $this->aggregateRootVersion = $snapshot->aggregateRootVersion();

        $this->paymentIntentId = new GenericId($snapshot->state()['paymentIntentId']);
        $this->status = DisputeStatusEnum::from($snapshot->state()['status']);
        $this->money = new Money(
            $snapshot->state()['money']['amount'],
            new Currency($snapshot->state()['money']['currency']),
        );
        $this->fee = new Money(
            $snapshot->state()['fee']['amount'],
            new Currency($snapshot->state()['fee']['currency']),
        );
        $this->reason = $snapshot->state()['reason'];
    }

    protected function createSnapshotState(): array
    {
        return [
            'paymentIntentId' => $this->paymentIntentId->toString(),
            'status' => $this->status->value,
            'money' => $this->money->jsonSerialize(),
            'fee' => $this->fee->jsonSerialize(),
            'reason' => $this->reason,
        ];
    }
}