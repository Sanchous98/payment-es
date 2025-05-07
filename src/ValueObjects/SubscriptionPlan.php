<?php

declare(strict_types=1);

namespace PaymentSystem\ValueObjects;

use DateInterval;
use Money\Money;

readonly class SubscriptionPlan
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