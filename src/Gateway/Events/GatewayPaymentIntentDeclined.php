<?php

namespace PaymentSystem\Gateway\Events;

use PaymentSystem\Gateway\Resources\PaymentIntentInterface;

readonly class GatewayPaymentIntentDeclined
{
    public function __construct(public PaymentIntentInterface $paymentIntent)
    {
    }
}