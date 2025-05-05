<?php

namespace PaymentSystem\Tests;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\EventSourcing\TestUtilities\AggregateRootTestCase;
use PaymentSystem\SubscriptionAggregateRoot;
use PaymentSystem\ValueObjects\GenericId;

class Subscriptions extends AggregateRootTestCase
{
    protected function newAggregateRootId(): AggregateRootId
    {
        return new GenericId(1);
    }

    protected function aggregateRootClassName(): string
    {
        return SubscriptionAggregateRoot::class;
    }

    protected function handle(\Closure $closure): void
    {
        $subscription = $closure($this->retrieveAggregateRoot($this->newAggregateRootId()));
        $this->persistAggregateRoot($subscription);
    }

    protected function messageDispatcher(): MessageDispatcher
    {
        return new SynchronousMessageDispatcher();
    }
}