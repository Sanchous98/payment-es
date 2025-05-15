<?php

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Entities\SubscriptionPlan;
use PaymentSystem\PaymentMethodAggregateRoot;

interface CreateSubscriptionCommandInterface
{
    public AggregateRootId $id { get; }

    public SubscriptionPlan $plan { get; }

    public PaymentMethodAggregateRoot $paymentMethod { get; }
}