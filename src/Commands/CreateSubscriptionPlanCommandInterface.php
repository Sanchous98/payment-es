<?php

namespace PaymentSystem\Commands;

use DateInterval;
use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\ValueObjects\MerchantDescriptor;

interface CreateSubscriptionPlanCommandInterface
{
    public AggregateRootId $id { get; }

    public string $name { get; }

    public string $description { get; }

    public Money $money { get; }

    public DateInterval $interval { get; }

    public MerchantDescriptor $merchantDescriptor { get; }
}