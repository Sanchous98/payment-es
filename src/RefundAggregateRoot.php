<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\Commands\CreateRefundCommandInterface;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Enum\RefundStatusEnum;
use PaymentSystem\Events\RefundCanceled;
use PaymentSystem\Events\RefundCreated;
use PaymentSystem\Events\RefundDeclined;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\Exceptions\RefundException;

class RefundAggregateRoot implements AggregateRoot
{
    use AggregateRootBehaviour;

    private(set) AggregateRootId $paymentIntentId;

    private(set) Money $money;

    private(set) RefundStatusEnum $status;

    private(set) ?string $declineReason = null;

    public static function create(CreateRefundCommandInterface $command): self
    {
        $command->money->isZero() && throw InvalidAmountException::notZero();
        $command->money->isNegative() && throw InvalidAmountException::notNegative();
        $command->paymentIntent->is(PaymentIntentStatusEnum::SUCCEEDED) || throw RefundException::unsupportedIntentStatus($command->paymentIntent->status);
        $command->paymentIntent->money->lessThan($command->money) && throw InvalidAmountException::notGreaterThanCaptured($command->money->getAmount());

        $self = new static($command->id);
        $self->recordThat(new RefundCreated($command->money, $command->paymentIntent->aggregateRootId()));

        return $self;
    }

    public function is(RefundStatusEnum $status): bool
    {
        return $this->status === $status;
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

    protected function apply(object $event): void
    {
        switch ($event::class) {
            case RefundCreated::class:
                $this->applyRefundCreated($event);
                return;
            case RefundCanceled::class:
                $this->applyRefundCanceled();
                return;
            case RefundDeclined::class:
                $this->applyRefundDeclined($event);
                return;
        }
    }

    protected function applyRefundCreated(RefundCreated $event): void
    {
        $this->money = $event->money;
        $this->status = RefundStatusEnum::CREATED;
        $this->paymentIntentId = $event->paymentIntentId;
    }

    protected function applyRefundCanceled(): void
    {
        $this->status = RefundStatusEnum::CANCELED;
    }

    protected function applyRefundDeclined(RefundDeclined $event): void
    {
        $this->status = RefundStatusEnum::DECLINED;
        $this->declineReason = $event->reason;
    }
}