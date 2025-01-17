<?php

namespace PaymentSystem\Tests;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\EventSourcing\TestUtilities\AggregateRootTestCase;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\ValueObjects\GenericId;

class Tokens extends AggregateRootTestCase
{
    protected function newAggregateRootId(): AggregateRootId
    {
        return new GenericId(1);
    }

    protected function aggregateRootClassName(): string
    {
        return TokenAggregateRoot::class;
    }

    protected function handle(\Closure $closure): void
    {
        $token = $closure($this->retrieveAggregateRoot($this->newAggregateRootId()));
        $this->persistAggregateRoot($token);
    }

    protected function messageDispatcher(): MessageDispatcher
    {
        return new SynchronousMessageDispatcher();
    }
}