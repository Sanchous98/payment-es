<?php

declare(strict_types=1);

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\ValueObjects\SubscriptionPlan;

readonly class SubscriptionCreated
{
    public function __construct(public SubscriptionPlan $plan, public AggregateRootId $paymentMethodId)
    {
    }
}