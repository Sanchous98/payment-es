<?php

namespace PaymentSystem\Gateway\Events;

use PaymentSystem\Gateway\Resources\PaymentIntentInterface;

readonly class GatewayPaymentIntentAuthorized
{
    public function __construct(public PaymentIntentInterface $paymentIntent)
    {
    }
}