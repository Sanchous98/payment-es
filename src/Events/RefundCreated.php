<?php

declare(strict_types=1);

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;

readonly final class RefundCreated
{
    public function __construct(
        public Money $money,
        public AggregateRootId $paymentIntentId,
    ) {
    }
}