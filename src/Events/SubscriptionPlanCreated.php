<?php

namespace PaymentSystem\Events;

use DateInterval;
use Money\Money;
use PaymentSystem\ValueObjects\MerchantDescriptor;

readonly class SubscriptionPlanCreated
{
    public function __construct(
        public string $name,
        public string $description,
        public Money $money,
        public DateInterval $interval,
        public MerchantDescriptor $merchantDescriptor = new MerchantDescriptor(),
    ) {
    }
}