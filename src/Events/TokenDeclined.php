<?php

namespace PaymentSystem\Events;

readonly class TokenDeclined
{
    public function __construct(public string $reason)
    {
    }
}