<?php

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;

readonly final class PaymentIntentCaptured
{
    public function __construct(
        public ?string $amount = null,
        public ?AggregateRootId $tenderId = null,
    ) {
    }
}