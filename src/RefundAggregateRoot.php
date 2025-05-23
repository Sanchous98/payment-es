<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\AggregateRootWithAggregates;
use Money\Money;
use PaymentSystem\Commands\CreateRefundCommandInterface;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Enum\RefundStatusEnum;
use PaymentSystem\Events\RefundCanceled;
use PaymentSystem\Events\RefundCreated;
use PaymentSystem\Events\RefundDeclined;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\Exceptions\RefundException;
use PaymentSystem\Gateway\Events\GatewayRefundCreated;

class RefundAggregateRoot implements AggregateRoot
{
    use AggregateRootWithAggregates {
        __construct as private __aggregateRootConstruct;
    }

    private AggregateRootId $paymentIntentId;

    private Money $money;

    private RefundStatusEnum $status;

    private ?string $declineReason = null;

    private Gateway\RefundAggregate $gateway;

    private function __construct(AggregateRootId $id)
    {
        $this->__aggregateRootConstruct($id);
        $this->gateway = new Gateway\RefundAggregate($this->eventRecorder());
        $this->registerAggregate($this->gateway);
    }

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

    public function getGatewayRefund(): Gateway\RefundAggregate
    {
        return $this->gateway;
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

    public function __sleep()
    {
        unset($this->eventRecorder);
        return array_keys((array)$this);
    }

    public function __wakeup(): void
    {
        foreach ($this->aggregatesInsideRoot as $aggregate) {
            $aggregate->__construct($this->eventRecorder());
        }
    }

    protected function applyRefundCreated(RefundCreated $event): void
    {
        $this->money = $event->money;
        $this->status = RefundStatusEnum::CREATED;
        $this->paymentIntentId = $event->paymentIntentId;

        $this->gateway = new Gateway\RefundAggregate($this->eventRecorder());
        $this->registerAggregate($this->gateway);
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

    protected function applyGatewayRefundCreated(GatewayRefundCreated $event): void
    {
        $this->money = $event->refund->getMoney();
        $this->paymentIntentId = $event->refund->getPaymentIntentId();
        $this->status = RefundStatusEnum::SUCCEEDED;
    }

    protected function applyGatewayRefundCanceled(): void
    {
        $this->status = RefundStatusEnum::CANCELED;
    }
}