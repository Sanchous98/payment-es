<?php

namespace PaymentSystem\Exceptions;

use RuntimeException;

class CardException extends RuntimeException
{
    public static function expired(): static
    {
        return new static("Card expired");
    }
}