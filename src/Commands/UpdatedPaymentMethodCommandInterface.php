<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use PaymentSystem\Entities\BillingAddress;

interface UpdatedPaymentMethodCommandInterface
{
    public function getBillingAddress(): BillingAddress;
}