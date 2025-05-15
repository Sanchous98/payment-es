<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\AggregateRootWithAggregates;
use PaymentSystem\Commands\CreatePaymentMethodCommandInterface;
use PaymentSystem\Commands\CreateTokenPaymentMethodCommandInterface;
use PaymentSystem\Commands\UpdatedPaymentMethodCommandInterface;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Entities\BillingAddress;
use PaymentSystem\Enum\PaymentMethodStatusEnum;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodFailed;
use PaymentSystem\Events\PaymentMethodSuspended;
use PaymentSystem\Events\PaymentMethodUpdated;
use PaymentSystem\Exceptions\PaymentMethodException;
use PaymentSystem\Exceptions\TokenException;
use PaymentSystem\ValueObjects\ThreeDSResult;
use RuntimeException;

class PaymentMethodAggregateRoot implements AggregateRoot, TenderInterface
{
    use AggregateRootBehaviour;

    private BillingAddress $billingAddress;

    private SourceInterface $source;

    private PaymentMethodStatusEnum $status;

    private ?ThreeDSResult $threeDSResult;

    public function getBillingAddress(): BillingAddress
    {
        return $this->billingAddress;
    }

    public function getSource(): SourceInterface
    {
        return $this->source;
    }

    public function isValid(): bool
    {
        return $this->is(PaymentMethodStatusEnum::SUCCEEDED);
    }

    public function is(PaymentMethodStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public static function create(CreatePaymentMethodCommandInterface $command): static
    {
        $self = new static($command->getId());
        $self->recordThat(new PaymentMethodCreated($command->getBillingAddress(), $command->getSource(), $command->getThreeDS()));

        return $self;
    }

    public static function createFromToken(CreateTokenPaymentMethodCommandInterface $command): static
    {
        !$command->getToken()->isValid() && throw TokenException::suspended();

        $self = new static($command->getId());
        $self->recordThat(
            new PaymentMethodCreated(
                $command->getToken()->getBillingAddress(),
                $command->getToken()->getSource(),
                tokenId: $command->getToken()->aggregateRootId()
            )
        );

        return $self;
    }

    public function update(UpdatedPaymentMethodCommandInterface $command): static
    {
        if ($this->status === PaymentMethodStatusEnum::FAILED || $this->status === PaymentMethodStatusEnum::SUSPENDED) {
            throw PaymentMethodException::suspended();
        }

        $this->recordThat(new PaymentMethodUpdated($command->getBillingAddress()));

        return $this;
    }

    public function fail(): static
    {
        if ($this->status !== PaymentMethodStatusEnum::PENDING) {
            throw new RuntimeException('Payment method is not pending to creating');
        }

        $this->recordThat(new PaymentMethodFailed());

        return $this;
    }

    public function suspend(): static
    {
        if ($this->status !== PaymentMethodStatusEnum::SUCCEEDED) {
            throw PaymentMethodException::suspended();
        }

        $this->recordThat(new PaymentMethodSuspended());

        return $this;
    }

    public function use(?callable $callback = null): static
    {
        $this->isValid() || throw PaymentMethodException::suspended();

        isset($callback) && $callback($this);

        return $this;
    }

    // Event Listener

    protected function applyPaymentMethodCreated(PaymentMethodCreated $event): void
    {
        $this->billingAddress = $event->billingAddress;
        $this->source = $event->source;
        $this->status = PaymentMethodStatusEnum::PENDING;
    }

    protected function applyPaymentMethodUpdated(PaymentMethodUpdated $event): void
    {
        $this->billingAddress = $event->billingAddress;
    }

    protected function applyPaymentMethodSuspended(): void
    {
        $this->status = PaymentMethodStatusEnum::SUSPENDED;
    }

    protected function applyPaymentMethodFailed(): void
    {
        $this->status = PaymentMethodStatusEnum::FAILED;
    }
}