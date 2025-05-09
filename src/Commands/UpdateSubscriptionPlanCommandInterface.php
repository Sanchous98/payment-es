<?php

namespace PaymentSystem\Commands;

use DateInterval;
use Money\Money;
use PaymentSystem\ValueObjects\MerchantDescriptor;

interface UpdateSubscriptionPlanCommandInterface
{
    public function getName(): ?string;

    public function getDescription(): ?string;

    public function getMoney(): ?Money;

    public function getInterval(): ?DateInterval;

    public function getMerchantDescriptor(): ?MerchantDescriptor;
}