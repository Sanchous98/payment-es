<?php

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use Money\Currency;
use Money\Money;
use PaymentSystem\Commands\CreateRefundCommandInterface;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Enum\RefundStatusEnum;
use PaymentSystem\Events\RefundCanceled;
use PaymentSystem\Events\RefundCreated;
use PaymentSystem\Events\RefundDeclined;
use PaymentSystem\Events\RefundSucceeded;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\Exceptions\RefundException;
use PaymentSystem\Exceptions\RefundUnavailableException;
use PaymentSystem\ValueObjects\GenericId;

class RefundAggregateRoot implements AggregateRootWithSnapshotting
{
    use SnapshotBehaviour;
    use AggregateRootBehaviour;

    private AggregateRootId $paymentIntentId;

    private Money $money;

    private RefundStatusEnum $status;

    private ?string $declineReason = null;

    public function getPaymentIntentId(): AggregateRootId
    {
        return $this->paymentIntentId;
    }

    public function getMoney(): Money
    {
        return $this->money;
    }

    public function is(RefundStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public function getDeclineReason(): ?string
    {
        return $this->declineReason;
    }

    public function create(CreateRefundCommandInterface $command): self
    {
        $command->getMoney()->isZero() && throw InvalidAmountException::notZero();
        $command->getMoney()->isNegative() && throw InvalidAmountException::notNegative();
        $command->getPaymentIntent()->is(PaymentIntentStatusEnum::SUCCEEDED) || throw RefundUnavailableException::unsupportedIntentStatus($command->getPaymentIntent()->getStatus());
        $command->getPaymentIntent()->getMoney()->lessThan($command->getMoney()) && throw InvalidAmountException::notGreaterThanCaptured($command->getMoney()->getAmount());

        $this->recordThat(new RefundCreated($command->getMoney(), $command->getPaymentIntent()->aggregateRootId()));

        return $this;
    }

    public function success(): static
    {
        if ($this->status !== RefundStatusEnum::CREATED && $this->status !== RefundStatusEnum::REQUIRES_ACTION) {
            throw RefundException::cannotSucceed($this->status);
        }

        $this->recordThat(new RefundSucceeded());

        return $this;
    }

    public function decline(string $reason): static
    {
        if ($this->status !== RefundStatusEnum::CREATED && $this->status !== RefundStatusEnum::REQUIRES_ACTION) {
            throw RefundException::cannotDecline($this->status);
        }

        $this->recordThat(new RefundDeclined($reason));

        return $this;
    }

    public function cancel(): static
    {
        if ($this->status !== RefundStatusEnum::CREATED && $this->status !== RefundStatusEnum::REQUIRES_ACTION) {
            throw RefundException::cannotCancel($this->status);
        }

        $this->recordThat(new RefundCanceled());

        return $this;
    }

    protected function applyRefundCreated(RefundCreated $command): void
    {
        $this->money = $command->money;
        $this->status = RefundStatusEnum::CREATED;
        $this->paymentIntentId = $command->paymentIntentId;
    }

    protected function applyRefundSucceeded(): void
    {
        $this->status = RefundStatusEnum::SUCCEEDED;
    }

    protected function applyRefundDeclined(RefundDeclined $event): void
    {
        $this->status = RefundStatusEnum::DECLINED;
        $this->declineReason = $event->reason;
    }

    protected function applyRefundCanceled(): void
    {
        $this->status = RefundStatusEnum::CANCELED;
    }

    protected function apply(RefundCreated|RefundCanceled|RefundDeclined|RefundSucceeded $event): void
    {
        match ($event::class) {
            RefundCreated::class => $this->applyRefundCreated($event),
            RefundCanceled::class => $this->applyRefundCanceled(),
            RefundDeclined::class => $this->applyRefundDeclined($event),
            RefundSucceeded::class => $this->applyRefundSucceeded(),
        };

        ++$this->aggregateRootVersion;
    }

    protected function applySnapshot(Snapshot $snapshot): void
    {
        $this->aggregateRootVersion = $snapshot->aggregateRootVersion();

        $this->paymentIntentId = new GenericId($snapshot->state()['paymentIntentId']);
        $this->money = new Money($snapshot->state()['money']['amount'], new Currency($snapshot->state()['money']['currency']));
        $this->status = RefundStatusEnum::from($snapshot->state()['status']);
        $this->declineReason = $snapshot->state()['declineReason'];
    }

    protected function createSnapshotState(): array
    {
        return [
            'paymentIntentId' => $this->paymentIntentId->toString(),
            'money' => $this->money->jsonSerialize(),
            'status' => $this->status->value,
            'declineReason' => $this->declineReason,
        ];
    }
}