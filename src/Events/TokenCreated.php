<?php

namespace PaymentSystem\Events;

use PaymentSystem\ValueObjects\CreditCard;

readonly class TokenCreated
{
    public function __construct(public CreditCard $card)
    {
    }
}