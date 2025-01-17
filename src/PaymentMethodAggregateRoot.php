<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\AggregateRootWithAggregates;
use PaymentSystem\Commands\CreatePaymentMethodCommandInterface;
use PaymentSystem\Commands\CreateTokenPaymentMethodCommandInterface;
use PaymentSystem\Commands\UpdatedPaymentMethodCommandInterface;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Enum\PaymentMethodStatusEnum;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodFailed;
use PaymentSystem\Events\PaymentMethodSuspended;
use PaymentSystem\Events\PaymentMethodUpdated;
use PaymentSystem\Exceptions\PaymentMethodSuspendedException;
use PaymentSystem\Exceptions\TokenExpiredException;
use PaymentSystem\Gateway\Events\GatewayPaymentMethodAdded;
use PaymentSystem\Gateway\Events\GatewayPaymentMethodSuspended;
use PaymentSystem\Gateway\Events\GatewayPaymentMethodUpdated;
use PaymentSystem\Gateway\Resources\PaymentMethodInterface;
use PaymentSystem\ValueObjects\BillingAddress;
use RuntimeException;

class PaymentMethodAggregateRoot implements AggregateRoot, TenderInterface
{
    use AggregateRootWithAggregates {
        __construct as __aggregateRootConstruct;
    }

    private BillingAddress $billingAddress;

    private SourceInterface $source;

    private PaymentMethodStatusEnum $status;

    private Gateway\PaymentMethodsAggregate $gateway;

    private function __construct(AggregateRootId $aggregateRootId)
    {
        $this->__aggregateRootConstruct($aggregateRootId);
        $this->gateway = new Gateway\PaymentMethodsAggregate($this->eventRecorder());
        $this->registerAggregate($this->gateway);
    }

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

    public function getGatewayPaymentMethods(): Gateway\PaymentMethodsAggregate
    {
        return $this->gateway;
    }

    public function getGatewayTenders(): array
    {
        return array_merge(...array_values($this->gateway->getPaymentMethods()));
    }

    public static function create(CreatePaymentMethodCommandInterface $command): static
    {
        $self = new static($command->getId());
        $self->recordThat(new PaymentMethodCreated($command->getBillingAddress(), $command->getSource()));

        return $self;
    }

    public static function createFromToken(CreateTokenPaymentMethodCommandInterface $command): static
    {
        !$command->getToken()->isValid() && throw new TokenExpiredException();

        $self = new static($command->getId());
        $self->recordThat(
            new PaymentMethodCreated(
                $command->getBillingAddress(),
                $command->getToken()->getCard(),
                $command->getToken()->aggregateRootId()
            )
        );

        return $self;
    }

    public function update(UpdatedPaymentMethodCommandInterface $command): static
    {
        if ($this->status === PaymentMethodStatusEnum::FAILED || $this->status === PaymentMethodStatusEnum::SUSPENDED) {
            throw new PaymentMethodSuspendedException();
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
            throw new PaymentMethodSuspendedException();
        }

        $this->recordThat(new PaymentMethodSuspended());

        return $this;
    }

    public function use(?callable $callback = null): static
    {
        if (!$this->isValid()) {
            throw new PaymentMethodSuspendedException();
        }

        isset($callback) && $callback($this);

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

    protected function applyGatewayPaymentMethodAdded(GatewayPaymentMethodAdded $event): void
    {
        $this->billingAddress = $event->paymentMethod->getBillingAddress();
        $this->source = $event->paymentMethod->getSource();
        $this->status = PaymentMethodStatusEnum::SUCCEEDED;
    }

    protected function applyGatewayPaymentMethodUpdated(GatewayPaymentMethodUpdated $event): void
    {
        $this->billingAddress = $event->paymentMethod->getBillingAddress();
    }

    protected function applyGatewayPaymentMethodSuspended(GatewayPaymentMethodSuspended $event): void
    {
        $paymentMethods = array_map(function (PaymentMethodInterface $paymentMethod) use ($event) {
            return $event->paymentMethod->getId()->toString() === $paymentMethod->getId()->toString() ? $event->paymentMethod : $paymentMethod;
        }, $this->getGatewayTenders());

        $validMethods = array_filter($paymentMethods, fn(PaymentMethodInterface $paymentMethod) => $paymentMethod->isValid());

        if (count($validMethods) === 0) {
            $this->status = PaymentMethodStatusEnum::SUSPENDED;
        }
    }
}