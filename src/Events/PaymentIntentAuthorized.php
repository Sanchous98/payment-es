<?php

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\ValueObjects\ThreeDSResult;

readonly final class PaymentIntentAuthorized
{
    public function __construct(
        public Money $money,
        public ?AggregateRootId $tenderId = null,
        public string $merchantDescriptor = '',
        public string $description = '',
        public ?ThreeDSResult $threeDSResult = null,
    ) {
    }
}