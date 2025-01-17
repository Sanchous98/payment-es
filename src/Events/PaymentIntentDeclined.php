<?php

declare(strict_types=1);

namespace PaymentSystem\Events;

readonly final class PaymentIntentDeclined
{
    public function __construct(public string $reason)
    {
    }
}