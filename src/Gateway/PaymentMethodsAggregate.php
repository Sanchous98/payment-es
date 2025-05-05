<?php

namespace PaymentSystem\Gateway;

use EventSauce\EventSourcing\AggregateAppliesKnownEvents;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\EventRecorder;
use EventSauce\EventSourcing\EventSourcedAggregate;
use PaymentSystem\Gateway\Events\GatewayPaymentMethodSuspended;
use PaymentSystem\Gateway\Events\GatewayPaymentMethodUpdated;
use PaymentSystem\Gateway\Resources\PaymentMethodInterface;

class PaymentMethodsAggregate implements EventSourcedAggregate
{
    use AggregateAppliesKnownEvents;

    private array $paymentMethods = [];

    public function __construct(private EventRecorder $eventRecorder)
    {
    }

    /**
     * @param callable(self): PaymentMethodInterface $callback
     */
    public function add(callable $callback): static
    {
        $paymentMethod = $callback($this);
        assert($paymentMethod instanceof PaymentMethodInterface);
        $paymentMethod->isValid(); // TODO: throw

        $this->eventRecorder->recordThat(new Events\GatewayPaymentMethodAdded($paymentMethod));

        return $this;
    }

    /**
     * @param callable(PaymentMethodInterface, self): PaymentMethodInterface $callback
     */
    public function update(AggregateRootId $gatewayId, AggregateRootId $id, callable $callback): static
    {
        $old = $this->paymentMethods[$gatewayId->toString()][$id->toString()];
        $new = $callback($old, $this);
        $new->isValid(); // TODO: throw

        $this->eventRecorder->recordThat(new GatewayPaymentMethodUpdated($new));

        return $this;
    }

    public function suspend(AggregateRootId $gatewayId, AggregateRootId $id, callable $callback): static
    {
        $old = $this->paymentMethods[$gatewayId->toString()][$id->toString()];
        $new = $callback($old, $this);

        $this->eventRecorder->recordThat(new GatewayPaymentMethodSuspended($new));

        return $this;
    }

    /**
     * @param callable(PaymentMethodInterface): bool $callback
     */
    public function find(callable $callback): ?PaymentMethodInterface
    {
        foreach ($this->paymentMethods as $gateway) {
            foreach ($gateway as $paymentMethod) {
                if ($callback($paymentMethod)) {
                    return $paymentMethod;
                }
            }
        }

        return null;
    }

    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    protected function applyGatewayPaymentMethodAdded(Events\GatewayPaymentMethodAdded $event): void
    {
        $this->paymentMethods[$event->paymentMethod->getGatewayId()->toString()][$event->paymentMethod->getId()->toString()] = $event->paymentMethod;
    }

    protected function applyGatewayPaymentMethodUpdated(Events\GatewayPaymentMethodUpdated $event): void
    {
        $this->paymentMethods[$event->paymentMethod->getGatewayId()->toString()][$event->paymentMethod->getId()->toString()] = $event->paymentMethod;
    }

    protected function applyGatewayPaymentMethodSuspended(Events\GatewayPaymentMethodSuspended $event): void
    {
        $this->paymentMethods[$event->paymentMethod->getGatewayId()->toString()][$event->paymentMethod->getId()->toString()] = $event->paymentMethod;
    }

    public function __sleep(): array
    {
        unset($this->eventRecorder);
        return array_keys((array)$this);
    }
}