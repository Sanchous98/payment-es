<?php

namespace PaymentSystem\Gateway;

use EventSauce\EventSourcing\AggregateAppliesKnownEvents;
use EventSauce\EventSourcing\EventRecorder;
use EventSauce\EventSourcing\EventSourcedAggregate;
use PaymentSystem\Gateway\Events\GatewayRefundCanceled;
use PaymentSystem\Gateway\Events\GatewayRefundCreated;
use PaymentSystem\Gateway\Resources\RefundInterface;

class RefundAggregate implements EventSourcedAggregate
{
    use AggregateAppliesKnownEvents;

    private RefundInterface $refund;

    public function __construct(private EventRecorder $eventRecorder)
    {
    }

    /**
     * @param callable(self): RefundInterface $callback
     */
    public function create(callable $callback): static
    {
        $refund = $callback($this);
        assert($refund instanceof RefundInterface);
        $refund->isValid(); // TODO

        $this->eventRecorder->recordThat(new GatewayRefundCreated($refund));

        return $this;
    }

    /**
     * @param callable(RefundInterface, self): RefundInterface $callback
     */
    public function cancel(callable $callback): static
    {
        $new = $callback($this->refund, $this);
        $new->isValid(); // TODO: throw

        $this->eventRecorder->recordThat(new GatewayRefundCanceled($new));

        return $this;
    }

    public function __sleep(): array
    {
        unset($this->eventRecorder);
        return array_keys((array)$this);
    }
}