<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use PaymentSystem\ValueObjects\BillingAddress;

interface UpdatedPaymentMethodCommandInterface
{
    public function getBillingAddress(): BillingAddress;
}