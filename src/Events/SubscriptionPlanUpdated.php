<?php

namespace PaymentSystem\Events;

use DateInterval;
use Money\Money;
use PaymentSystem\ValueObjects\MerchantDescriptor;

readonly class SubscriptionPlanUpdated
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?Money $money = null,
        public ?DateInterval $interval = null,
        public ?MerchantDescriptor $merchantDescriptor = null,
    ) {
    }
}