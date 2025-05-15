<?php

namespace PaymentSystem\Events;

readonly class GatewayTokenAdded
{
    public function __construct(public mixed $metadata = null)
    {
    }
}