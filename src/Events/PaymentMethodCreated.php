<?php

namespace PaymentSystem\Events;

use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\SourceInterface;

readonly final class PaymentMethodCreated
{
    public function __construct(
        public BillingAddress $billingAddress,
        public SourceInterface $source,
    ) {
    }
}