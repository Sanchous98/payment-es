<?php

namespace PaymentSystem\Events;

use PaymentSystem\Contracts\TokenizedSourceInterface;

readonly class TokenCreated
{
    public function __construct(public TokenizedSourceInterface $source)
    {
    }
}