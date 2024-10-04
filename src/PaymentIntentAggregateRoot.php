<?php

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use Money\Currency;
use Money\Money;
use PaymentSystem\Commands\AuthorizePaymentCommandInterface;
use PaymentSystem\Commands\CapturePaymentCommandInterface;
use PaymentSystem\Enum\ECICodesEnum;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Enum\SupportedVersionsEnum;
use PaymentSystem\Enum\ThreeDSStatusEnum;
use PaymentSystem\Events\PaymentIntentAuthorized;
use PaymentSystem\Events\PaymentIntentCanceled;
use PaymentSystem\Events\PaymentIntentCaptured;
use PaymentSystem\Events\PaymentIntentDeclined;
use PaymentSystem\Exceptions\CancelUnavailableException;
use PaymentSystem\Exceptions\CaptureUnavailableException;
use PaymentSystem\Exceptions\CardExpiredException;
use PaymentSystem\Exceptions\DeclineUnavailableException;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\Exceptions\PaymentMethodSuspendedException;
use PaymentSystem\ValueObjects\GenericId;
use PaymentSystem\ValueObjects\ThreeDSResult;

class PaymentIntentAggregateRoot implements AggregateRootWithSnapshotting
{
    use SnapshotBehaviour;
    use AggregateRootBehaviour;

    private const CAPTURABLE_STATUSES = [
        PaymentIntentStatusEnum::REQUIRES_CAPTURE,
        PaymentIntentStatusEnum::REQUIRES_PAYMENT_METHOD,
    ];

    private AggregateRootId $tenderId;

    private PaymentIntentStatusEnum $status;

    private Money $money;

    private string $merchantDescriptor;

    private string $description;

    private string $declineReason = '';

    private ?ThreeDSResult $threeDSResult;

    private Money $authCaptureDiff;

    public function is(PaymentIntentStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public function getPaymentMethodId(): ?AggregateRootId
    {
        return $this->paymentMethodId ?? null;
    }

    public function getStatus(): PaymentIntentStatusEnum
    {
        return $this->status;
    }

    public function getMoney(): Money
    {
        return $this->money;
    }

    public function getMerchantDescriptor(): string
    {
        return $this->merchantDescriptor;
    }

    public function getDeclineReason(): string
    {
        return $this->declineReason;
    }

    public function getThreeDSResult(): ?ThreeDSResult
    {
        return $this->threeDSResult;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAuthAndCaptureDifference(): Money
    {
        return $this->authCaptureDiff;
    }

    public static function authorize(AuthorizePaymentCommandInterface $command): static
    {
        $command->getTender()->isValid() || throw new PaymentMethodSuspendedException();

        $source = $command->getTender()->getSource();

        $source->isValid() || throw new CardExpiredException();
        $command->getMoney()->isZero() && throw InvalidAmountException::notZero();
        $command->getMoney()->isNegative() && throw InvalidAmountException::notNegative();

        $self = new static($command->getId());
        $self->recordThat(
            new PaymentIntentAuthorized(
                $command->getMoney(),
                $command->getTender()?->aggregateRootId(),
                $command->getMerchantDescriptor(),
                $command->getDescription(),
                $command->getThreeDSResult(),
            )
        );

        return $self;
    }

    public function capture(CapturePaymentCommandInterface $command): static
    {
        if (!in_array($this->status, self::CAPTURABLE_STATUSES, true)) {
            throw CaptureUnavailableException::unsupportedIntentStatus($this->status);
        }

        if ($this->status === PaymentIntentStatusEnum::REQUIRES_PAYMENT_METHOD) {
            $command->getTender() !== null || throw CaptureUnavailableException::paymentMethodIsRequired();
            $command->getTender()->isValid() || throw new PaymentMethodSuspendedException();
        }

        $money = new Money($command->getAmount() ?? $this->money->getAmount(), $this->money->getCurrency());

        $money->isZero() && throw InvalidAmountException::notZero();
        $money->isNegative() && throw InvalidAmountException::notNegative();

        if ($this->money->lessThan($money)) {
            throw InvalidAmountException::notGreaterThanAuthorized($this->money->getAmount());
        }

        $this->recordThat(
            new PaymentIntentCaptured(
                $command->getAmount(),
                isset($this->paymentMethodId) ? null : $command->getTender()?->aggregateRootId(),
            )
        );

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

    protected function applyPaymentIntentAuthorized(PaymentIntentAuthorized $event): void
    {
        $this->money = $event->money;
        $this->merchantDescriptor = $event->merchantDescriptor;
        $this->description = $event->description;
        $this->threeDSResult = $event->threeDSResult;
        $this->tenderId = $event->tenderId;
        $this->status = isset($event->tenderId) ? PaymentIntentStatusEnum::REQUIRES_CAPTURE : PaymentIntentStatusEnum::REQUIRES_PAYMENT_METHOD;
    }

    protected function applyPaymentIntentCaptured(PaymentIntentCaptured $event): void
    {
        $newMoney = isset($event->amount) ? new Money($event->amount, $this->money->getCurrency()) : $this->money;
        $this->authCaptureDiff = $this->money->subtract($newMoney);
        $this->money = $newMoney;
        $this->status = PaymentIntentStatusEnum::SUCCEEDED;
    }

    protected function applyPaymentIntentCanceled(): void
    {
        $this->status = PaymentIntentStatusEnum::CANCELED;
    }

    protected function applyPaymentIntentDeclined(PaymentIntentDeclined $event): void
    {
        $this->status = PaymentIntentStatusEnum::DECLINED;
        $this->declineReason = $event->reason;
    }

    protected function apply(object $event): void
    {
        switch ($event::class) {
            case PaymentIntentAuthorized::class:
                $this->applyPaymentIntentAuthorized($event);
                break;
            case PaymentIntentCaptured::class:
                $this->applyPaymentIntentCaptured($event);
                break;
            case PaymentIntentCanceled::class:
                $this->applyPaymentIntentCanceled();
                break;
            case PaymentIntentDeclined::class:
                $this->applyPaymentIntentDeclined($event);
                break;
        }

        ++$this->aggregateRootVersion;
    }

    protected function applySnapshot(Snapshot $snapshot): void
    {
        $this->aggregateRootVersion = $snapshot->aggregateRootVersion();

        $this->tenderId = new GenericId($snapshot->state()['tenderId']);
        $this->status = PaymentIntentStatusEnum::from($snapshot->state()['state']);
        $this->money = new Money(
            $snapshot->state()['money']['amount'],
            new Currency($snapshot->state()['money']['currency'])
        );
        $this->merchantDescriptor = $snapshot->state()['merchantDescriptor'];
        $this->description = $snapshot->state()['description'];
        $this->declineReason = $snapshot->state()['declineReason'];
        $this->threeDSResult = new ThreeDSResult(
            ThreeDSStatusEnum::from($snapshot->state()['threeDSResult']['status']),
            $snapshot->state()['threeDSResult']['authenticationValue'],
            ECICodesEnum::from($snapshot->state()['threeDSResult']['eci']),
            $snapshot->state()['threeDSResult']['dsTransactionId'],
            $snapshot->state()['threeDSResult']['acsTransactionId'],
            $snapshot->state()['threeDSResult']['cardToken'] ?? null,
            SupportedVersionsEnum::from($snapshot->state()['threeDSResult']['version'])
        );
    }

    protected function createSnapshotState(): array
    {
        return [
            'tenderId' => $this->tenderId->toString(),
            'status' => $this->status->value,
            'money' => $this->money->jsonSerialize(),
            'merchantDescriptor' => $this->merchantDescriptor,
            'description' => $this->description,
            'declineReason' => $this->declineReason,
            'threeDSResult' => $this->threeDSResult->jsonSerialize(),
        ];
    }
}