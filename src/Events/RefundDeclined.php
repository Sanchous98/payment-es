<?php

declare(strict_types=1);

namespace PaymentSystem\Events;

readonly final class RefundDeclined
{
    public function __construct(public string $reason)
    {
    }
}