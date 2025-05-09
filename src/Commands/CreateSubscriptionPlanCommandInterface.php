<?php

namespace PaymentSystem\Commands;

use DateInterval;
use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\ValueObjects\MerchantDescriptor;

interface CreateSubscriptionPlanCommandInterface
{
    public function getId(): AggregateRootId;

    public function getName(): string;

    public function getDescription(): string;

    public function getMoney(): Money;

    public function getInterval(): DateInterval;

    public function getMerchantDescriptor(): MerchantDescriptor;
}