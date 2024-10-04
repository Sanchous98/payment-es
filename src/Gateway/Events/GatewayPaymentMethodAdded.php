<?php

namespace PaymentSystem\Gateway\Events;

use PaymentSystem\Gateway\Resources\PaymentMethodInterface;

readonly class GatewayPaymentMethodAdded
{
    public function __construct(public PaymentMethodInterface $paymentMethod)
    {
    }
}