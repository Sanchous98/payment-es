<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\AggregateRootWithAggregates;
use Money\Money;
use PaymentSystem\Commands\AuthorizePaymentCommandInterface;
use PaymentSystem\Commands\CapturePaymentCommandInterface;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Events\PaymentIntentAuthorized;
use PaymentSystem\Events\PaymentIntentCanceled;
use PaymentSystem\Events\PaymentIntentCaptured;
use PaymentSystem\Events\PaymentIntentDeclined;
use PaymentSystem\Exceptions\CancelUnavailableException;
use PaymentSystem\Exceptions\CaptureUnavailableException;
use PaymentSystem\Exceptions\DeclineUnavailableException;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\Gateway\Events\GatewayPaymentIntentAuthorized;
use PaymentSystem\Gateway\Events\GatewayPaymentIntentCaptured;
use PaymentSystem\ValueObjects\MerchantDescriptor;
use PaymentSystem\ValueObjects\ThreeDSResult;

class PaymentIntentAggregateRoot implements AggregateRoot
{
    use AggregateRootWithAggregates {
        __construct as __aggregateRootConstruct;
    }

    private const CAPTURABLE_STATUSES = [
        PaymentIntentStatusEnum::REQUIRES_CAPTURE,
        PaymentIntentStatusEnum::REQUIRES_PAYMENT_METHOD,
    ];

    private AggregateRootId $tenderId;

    private PaymentIntentStatusEnum $status;

    private Money $money;

    private MerchantDescriptor $merchantDescriptor;

    private string $description;

    private string $declineReason = '';

    private ?ThreeDSResult $threeDSResult;

    private Money $authCaptureDiff;

    private Gateway\PaymentIntentAggregate $gateway;

    private ?AggregateRootId $subscriptionId = null;

    private function __construct(AggregateRootId $aggregateRootId)
    {
        $this->__aggregateRootConstruct($aggregateRootId);
        $this->gateway = new Gateway\PaymentIntentAggregate($this->eventRecorder());
        $this->registerAggregate($this->gateway);
    }

    public function getTenderId(): ?AggregateRootId
    {
        return $this->tenderId;
    }

    public function getMoney(): Money
    {
        return $this->money;
    }

    public function getMerchantDescriptor(): MerchantDescriptor
    {
        return $this->merchantDescriptor;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getThreeDSResult(): ?ThreeDSResult
    {
        return $this->threeDSResult;
    }

    public function is(PaymentIntentStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public function getStatus(): PaymentIntentStatusEnum
    {
        return $this->status;
    }

    public function getDeclineReason(): string
    {
        return $this->declineReason;
    }

    public function getSubscriptionId(): ?AggregateRootId
    {
        return $this->subscriptionId;
    }

    public function getAuthAndCaptureDifference(): Money
    {
        return $this->authCaptureDiff;
    }

    public function getGatewayPaymentIntent(): Gateway\PaymentIntentAggregate
    {
        return $this->gateway;
    }

    public static function authorize(AuthorizePaymentCommandInterface $command): static
    {
        $command->getMoney()->isZero() && throw InvalidAmountException::notZero();
        $command->getMoney()->isNegative() && throw InvalidAmountException::notNegative();

        if ($command->getTender() !== null) {
            $command->getTender()->use();
        }

        $self = new static($command->getId());
        $self->recordThat(new PaymentIntentAuthorized(
            $command->getMoney(),
            $command->getTender()?->aggregateRootId(),
            $command->getMerchantDescriptor(),
            $command->getDescription(),
            $command->getThreeDSResult(),
            $command->getSubscription()?->aggregateRootId(),
        ));

        return $self;
    }

    public function capture(CapturePaymentCommandInterface $command): static
    {
        if (!in_array($this->status, self::CAPTURABLE_STATUSES, true)) {
            throw CaptureUnavailableException::unsupportedIntentStatus($this->status);
        }

        $money = new Money($command->getAmount() ?? $this->money->getAmount(), $this->money->getCurrency());

        $money->isZero() && throw InvalidAmountException::notZero();
        $money->isNegative() && throw InvalidAmountException::notNegative();

        if ($this->money->lessThan($money)) {
            throw InvalidAmountException::notGreaterThanAuthorized($this->money->getAmount());
        }

        if ($this->status === PaymentIntentStatusEnum::REQUIRES_PAYMENT_METHOD) {
            $command->getTender() !== null || throw CaptureUnavailableException::paymentMethodIsRequired();
            $command->getTender()->use();
        }

        $this->recordThat(new PaymentIntentCaptured(
            $command->getAmount(),
            isset($this->paymentMethodId) ? null : $command->getTender()?->aggregateRootId(),
        ));

        return $this;
    }

    public function cancel(): static
    {
        if (!in_array($this->status, self::CAPTURABLE_STATUSES, true)) {
            throw CancelUnavailableException::unsupportedIntentStatus($this->status);
        }

        $this->recordThat(new PaymentIntentCanceled());

        return $this;
    }

    public function decline(string $reason): static
    {
        if (!in_array($this->status, self::CAPTURABLE_STATUSES, true)) {
            throw DeclineUnavailableException::unsupportedIntentStatus($this->status);
        }

        $this->recordThat(new PaymentIntentDeclined($reason));

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

    // Event Listeners

    protected function applyPaymentIntentAuthorized(PaymentIntentAuthorized $event): void
    {
        $this->onAuthorized(
            $event->money,
            $event->merchantDescriptor,
            $event->description,
            $event->threeDSResult,
            $event->tenderId
        );
        $this->gateway = new Gateway\PaymentIntentAggregate($this->eventRecorder());
        $this->registerAggregate($this->gateway);
    }

    protected function applyPaymentIntentCaptured(PaymentIntentCaptured $event): void
    {
        $this->onCaptured($event->amount, $event->tenderId);
    }

    protected function applyPaymentIntentCanceled(): void
    {
        $this->onCanceled();
    }

    protected function applyPaymentIntentDeclined(PaymentIntentDeclined $event): void
    {
        $this->onDeclined($event->reason);
    }

    protected function applyGatewayPaymentIntentAuthorized(GatewayPaymentIntentAuthorized $event): void
    {
        $this->onAuthorized(
            $event->paymentIntent->getMoney(),
            $event->paymentIntent->getMerchantDescriptor(),
            $event->paymentIntent->getDescription(),
            $event->paymentIntent->getThreeDS(),
            $event->paymentIntent->getPaymentMethodId(),
        );
    }

    protected function applyGatewayPaymentIntentCaptured(GatewayPaymentIntentCaptured $event): void
    {
        $this->onCaptured($event->paymentIntent->getMoney()->getAmount(), $event->paymentIntent->getPaymentMethodId());
    }

    protected function applyGatewayPaymentIntentCanceled(): void
    {
        $this->onCanceled();
    }

    private function onAuthorized(Money $money, MerchantDescriptor $merchantDescriptor, string $description, ?ThreeDSResult $threeDS = null, ?AggregateRootId $paymentMethodId = null): void
    {
        $this->money = $money;
        $this->merchantDescriptor = $merchantDescriptor;
        $this->description = $description;
        $this->threeDSResult = $threeDS;
        if (isset($paymentMethodId)) {
            $this->tenderId = $paymentMethodId;
        }
        $this->status = isset($this->tenderId) ? PaymentIntentStatusEnum::REQUIRES_CAPTURE : PaymentIntentStatusEnum::REQUIRES_PAYMENT_METHOD;
    }

    private function onCaptured(string $amount = null, AggregateRootId $tenderId = null): void
    {
        $newMoney = isset($amount) ? new Money($amount, $this->money->getCurrency()) : $this->money;
        $this->authCaptureDiff = $this->money->subtract($newMoney);
        $this->money = $newMoney;
        $this->tenderId = $tenderId ?? $this->tenderId;
        $this->status = PaymentIntentStatusEnum::SUCCEEDED;
    }

    private function onCanceled(): void
    {
        $this->status = PaymentIntentStatusEnum::CANCELED;
    }

    private function onDeclined(string $reason): void
    {
        $this->status = PaymentIntentStatusEnum::DECLINED;
        $this->declineReason = $reason;
    }
}