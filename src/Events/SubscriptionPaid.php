<?php

namespace PaymentSystem\Events;

use DateTimeImmutable;
use EventSauce\EventSourcing\AggregateRootId;

readonly class SubscriptionPaid
{
    public function __construct(public AggregateRootId $paymentIntentId, public DateTimeImmutable $when)
    {
    }
}