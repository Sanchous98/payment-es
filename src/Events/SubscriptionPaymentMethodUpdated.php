<?php

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;

readonly class SubscriptionPaymentMethodUpdated
{
    public function __construct(public AggregateRootId $paymentMethodId)
    {
    }
}