<?php

declare(strict_types=1);

namespace PaymentSystem\Events;

use PaymentSystem\Entities\BillingAddress;

readonly class PaymentMethodUpdated
{
    public function __construct(public BillingAddress $billingAddress)
    {
    }
}