<?php

namespace PaymentSystem\Events;

use PaymentSystem\ValueObjects\BillingAddress;

readonly class PaymentMethodUpdated
{
    public function __construct(public BillingAddress $billingAddress)
    {
    }
}