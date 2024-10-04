<?php

namespace PaymentSystem\Gateway\Events;

use PaymentSystem\Gateway\Resources\RefundInterface;

readonly class GatewayRefundCreated
{
    public function __construct(public RefundInterface $refund)
    {
    }
}