<?php

namespace PaymentSystem\Commands;

use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Source;

interface CreatePaymentMethodCommandInterface
{
    public function getBillingAddress(): BillingAddress;

    public function getSource(): Source;
}