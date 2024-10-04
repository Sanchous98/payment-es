<?php

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;

readonly final class DisputeCreated
{
    public function __construct(
        public AggregateRootId $paymentIntentId,
        public Money $money,
        public Money $fee,
        public string $reason,
    ) {
    }
}