<?php

declare(strict_types=1);

namespace PaymentSystem\Events;

readonly class TokenDeclined
{
    public function __construct(public string $reason)
    {
    }
}