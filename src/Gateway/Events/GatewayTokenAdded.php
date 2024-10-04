<?php

namespace PaymentSystem\Gateway\Events;

use PaymentSystem\Gateway\Resources\TokenInterface;

readonly class GatewayTokenAdded
{
    public function __construct(public TokenInterface $token)
    {
    }
}