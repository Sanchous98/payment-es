<?php

namespace PaymentSystem\Gateway;

use EventSauce\EventSourcing\AggregateAppliesKnownEvents;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\EventRecorder;
use EventSauce\EventSourcing\EventSourcedAggregate;
use PaymentSystem\Gateway\Events\GatewayPaymentIntentAuthorized;
use PaymentSystem\Gateway\Events\GatewayPaymentIntentCanceled;
use PaymentSystem\Gateway\Events\GatewayPaymentIntentCaptured;
use PaymentSystem\Gateway\Resources\PaymentIntentInterface;

class PaymentIntentAggregate implements EventSourcedAggregate
{
    use AggregateAppliesKnownEvents;

    private PaymentIntentInterface $paymentIntent;

    public function __construct(private EventRecorder $eventRecorder)
    {
    }

    /**
     * @param callable(self): PaymentIntentInterface $callback
     */
    public function authorize(callable $callback): static
    {
        $paymentIntent = $callback($this);
        assert($paymentIntent instanceof PaymentIntentInterface);
        $paymentIntent->isValid(); // TODO

        $this->eventRecorder->recordThat(new GatewayPaymentIntentAuthorized($paymentIntent));

        return $this;
    }

    /**
     * @param callable(PaymentIntentInterface, self): PaymentIntentInterface $callback
     */
    public function capture(callable $callback): static
    {
        $new = $callback($this->paymentIntent, $this);
        $new->isValid(); // TODO: throw

        $this->eventRecorder->recordThat(new GatewayPaymentIntentCaptured($new));

        return $this;
    }

    /**
     * @param callable(PaymentIntentInterface, self): PaymentIntentInterface $callback
     */
    public function cancel(callable $callback): static
    {
        $new = $callback($this->paymentIntent, $this);
        $new->isValid(); // TODO: throw

        $this->eventRecorder->recordThat(new GatewayPaymentIntentCanceled($new));

        return $this;
    }

    protected function applyGatewayPaymentIntentAuthorized(GatewayPaymentIntentAuthorized $event): void
    {
        $this->paymentIntent = $event->paymentIntent;
    }

    protected function applyGatewayPaymentIntentCaptured(GatewayPaymentIntentCaptured $event): void
    {
        $this->paymentIntent = $event->paymentIntent;
    }

    protected function applyGatewayPaymentIntentCanceled(GatewayPaymentIntentCanceled $event): void
    {
        $this->paymentIntent = $event->paymentIntent;
    }

    public function __sleep(): array
    {
        unset($this->eventRecorder);
        return array_keys((array)$this);
    }
}