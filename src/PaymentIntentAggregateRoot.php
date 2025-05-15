<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\Commands\AuthorizePaymentCommandInterface;
use PaymentSystem\Commands\CapturePaymentCommandInterface;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Events\PaymentIntentAuthorized;
use PaymentSystem\Events\PaymentIntentCanceled;
use PaymentSystem\Events\PaymentIntentCaptured;
use PaymentSystem\Events\PaymentIntentDeclined;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\Exceptions\PaymentIntentException;
use PaymentSystem\ValueObjects\MerchantDescriptor;
use PaymentSystem\ValueObjects\ThreeDSResult;

class PaymentIntentAggregateRoot implements AggregateRoot
{
    use AggregateRootBehaviour;

    private const array CAPTURABLE_STATUSES = [
        PaymentIntentStatusEnum::REQUIRES_CAPTURE,
        PaymentIntentStatusEnum::REQUIRES_PAYMENT_METHOD,
    ];

    private(set) AggregateRootId $tenderId;

    private(set) PaymentIntentStatusEnum $status;

    private(set) Money $money;

    private(set) MerchantDescriptor $merchantDescriptor;

    private(set) string $description;

    private(set) string $declineReason = '';

    private(set) ?ThreeDSResult $threeDSResult;

    private Money $authCaptureDiff;

    private(set) ?AggregateRootId $subscriptionId = null;

    public function is(PaymentIntentStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public function getAuthAndCaptureDifference(): Money
    {
        return $this->authCaptureDiff;
    }

    public static function authorize(AuthorizePaymentCommandInterface $command): static
    {
        $command->money->isZero() && throw InvalidAmountException::notZero();
        $command->money->isNegative() && throw InvalidAmountException::notNegative();
        $command->tender?->use();

        $self = new static($command->id);
        $self->recordThat(new PaymentIntentAuthorized(
            $command->money,
            $command->tender?->aggregateRootId(),
            $command->merchantDescriptor,
            $command->description,
            $command->threeDSResult,
            $command->subscription?->aggregateRootId(),
        ));

        return $self;
    }

    public function capture(CapturePaymentCommandInterface $command): static
    {
        if (!in_array($this->status, self::CAPTURABLE_STATUSES, true)) {
            throw PaymentIntentException::unsupportedIntentCaptureStatus($this->status);
        }

        $money = new Money($command->amount ?? $this->money->getAmount(), $this->money->getCurrency());

        $money->isZero() && throw InvalidAmountException::notZero();
        $money->isNegative() && throw InvalidAmountException::notNegative();

        if ($this->money->lessThan($money)) {
            throw InvalidAmountException::notGreaterThanAuthorized($this->money->getAmount());
        }

        if ($this->status === PaymentIntentStatusEnum::REQUIRES_PAYMENT_METHOD) {
            $command->tender !== null || throw PaymentIntentException::paymentMethodIsRequired();
            $command->tender->use();
        }

        $this->recordThat(new PaymentIntentCaptured(
            $command->amount,
            isset($this->paymentMethodId) ? null : $command->tender?->aggregateRootId(),
        ));

        return $this;
    }

    public function cancel(): static
    {
        if (!in_array($this->status, self::CAPTURABLE_STATUSES, true)) {
            throw PaymentIntentException::unsupportedIntentCancelStatus($this->status);
        }

        $this->recordThat(new PaymentIntentCanceled());

        return $this;
    }

    public function decline(string $reason): static
    {
        if (!in_array($this->status, self::CAPTURABLE_STATUSES, true)) {
            throw PaymentIntentException::unsupportedIntentDeclineStatus($this->status);
        }

        $this->recordThat(new PaymentIntentDeclined($reason));

        return $this;
    }

    // Event Listeners

    protected function applyPaymentIntentAuthorized(PaymentIntentAuthorized $event): void
    {
        $this->onAuthorized(
            $event->money,
            $event->merchantDescriptor,
            $event->description,
            $event->threeDSResult,
            $event->tenderId,
            $event->subscriptionId,
        );
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

    private function onAuthorized(Money $money, MerchantDescriptor $merchantDescriptor, string $description, ?ThreeDSResult $threeDS = null, ?AggregateRootId $paymentMethodId = null, ?AggregateRootId $subscriptionId = null): void
    {
        $this->money = $money;
        $this->merchantDescriptor = $merchantDescriptor;
        $this->description = $description;
        $this->threeDSResult = $threeDS;
        if (isset($paymentMethodId)) {
            $this->tenderId = $paymentMethodId;
        }
        $this->subscriptionId = $subscriptionId;
        $this->status = isset($this->tenderId) ? PaymentIntentStatusEnum::REQUIRES_CAPTURE : PaymentIntentStatusEnum::REQUIRES_PAYMENT_METHOD;
    }

    private function onCaptured(?string $amount = null, ?AggregateRootId $tenderId = null): void
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