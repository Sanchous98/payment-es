<?php

namespace PaymentSystem\Commands;

use PaymentSystem\ValueObjects\BillingAddress;

interface UpdatedPaymentMethodCommandInterface
{
    public function getBillingAddress(): BillingAddress;
}