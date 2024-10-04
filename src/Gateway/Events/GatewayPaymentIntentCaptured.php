<?php

namespace PaymentSystem\Gateway\Events;

use PaymentSystem\Gateway\Resources\PaymentIntentInterface;

readonly class GatewayPaymentIntentCaptured
{
    public function __construct(public PaymentIntentInterface $paymentIntent)
    {
    }
}