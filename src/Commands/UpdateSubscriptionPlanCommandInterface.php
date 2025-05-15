<?php

namespace PaymentSystem\Commands;

use DateInterval;
use Money\Money;
use PaymentSystem\ValueObjects\MerchantDescriptor;

interface UpdateSubscriptionPlanCommandInterface
{
    public ?string $name { get; }

    public ?string $description { get; }

    public ?Money $money { get; }

    public ?DateInterval $interval { get; }

    public ?MerchantDescriptor $merchantDescriptor { get; }
}