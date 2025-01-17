<?php

namespace PaymentSystem\Tests;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\EventSourcing\TestUtilities\AggregateRootTestCase;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\ValueObjects\GenericId;

class PaymentIntents extends AggregateRootTestCase
{
    protected function newAggregateRootId(): AggregateRootId
    {
        return new GenericId(1);
    }

    protected function aggregateRootClassName(): string
    {
        return PaymentIntentAggregateRoot::class;
    }

    protected function handle(\Closure $closure): void
    {
        $paymentIntent = $closure($this->retrieveAggregateRoot($this->newAggregateRootId()));
        $this->persistAggregateRoot($paymentIntent);
    }

    protected function messageDispatcher(): MessageDispatcher
    {
        return new SynchronousMessageDispatcher();
    }
}