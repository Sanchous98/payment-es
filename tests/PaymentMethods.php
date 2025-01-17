<?php

namespace PaymentSystem\Tests;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\EventSourcing\TestUtilities\AggregateRootTestCase;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\ValueObjects\GenericId;

class PaymentMethods extends AggregateRootTestCase
{
    protected function newAggregateRootId(): AggregateRootId
    {
        return new GenericId(1);
    }

    protected function aggregateRootClassName(): string
    {
        return PaymentMethodAggregateRoot::class;
    }

    protected function handle(\Closure $closure): void
    {
        $paymentMethod = $closure($this->retrieveAggregateRoot($this->newAggregateRootId()));
        $this->persistAggregateRoot($paymentMethod);
    }

    protected function messageDispatcher(): MessageDispatcher
    {
        return new SynchronousMessageDispatcher();
    }
}