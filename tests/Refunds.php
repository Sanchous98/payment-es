<?php

namespace PaymentSystem\Tests;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\EventSourcing\TestUtilities\AggregateRootTestCase;
use PaymentSystem\RefundAggregateRoot;
use PaymentSystem\ValueObjects\GenericId;

class Refunds extends AggregateRootTestCase
{
    protected function newAggregateRootId(): AggregateRootId
    {
        return new GenericId(1);
    }

    protected function aggregateRootClassName(): string
    {
        return RefundAggregateRoot::class;
    }

    protected function handle(\Closure $closure): void
    {
        $refund = $closure($this->retrieveAggregateRoot($this->newAggregateRootId()));
        $this->persistAggregateRoot($refund);
    }

    protected function messageDispatcher(): MessageDispatcher
    {
        return new SynchronousMessageDispatcher();
    }
}