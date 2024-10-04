<?php

namespace PaymentSystem\Commands;

use Money\Money;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\ValueObjects\ThreeDSResult;

interface AuthorizePaymentCommandInterface
{
    public function getMoney(): Money;

    public function getPaymentMethod(): ?PaymentMethodAggregateRoot;

    public function getMerchantDescriptor(): string;

    public function getDescription(): string;

    public function getThreeDSResult(): ?ThreeDSResult;
}