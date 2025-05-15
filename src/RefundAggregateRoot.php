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

    private AggregateRootId $paymentIntentId;

    private Money $money;

    private RefundStatusEnum $status;

    private ?string $declineReason = null;

    public static function create(CreateRefundCommandInterface $command): self
    {
        $command->getMoney()->isZero() && throw InvalidAmountException::notZero();
        $command->getMoney()->isNegative() && throw InvalidAmountException::notNegative();
        $command->getPaymentIntent()->is(PaymentIntentStatusEnum::SUCCEEDED) || throw RefundException::unsupportedIntentStatus($command->getPaymentIntent()->getStatus());
        $command->getPaymentIntent()->getMoney()->lessThan($command->getMoney()) && throw InvalidAmountException::notGreaterThanCaptured($command->getMoney()->getAmount());

        $self = new static($command->getId());
        $self->recordThat(new RefundCreated($command->getMoney(), $command->getPaymentIntent()->aggregateRootId()));

        return $self;
    }

    public function getMoney(): Money
    {
        return $this->money;
    }

    public function is(RefundStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public function getPaymentIntentId(): AggregateRootId
    {
        return $this->paymentIntentId;
    }

    public function getDeclineReason(): ?string
    {
        return $this->declineReason;
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