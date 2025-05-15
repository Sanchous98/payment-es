<?php

namespace PaymentSystem\Events;

readonly class GatewayTokenDeclined
{
    public function __construct(public mixed $metadata = null)
    {
    }
}