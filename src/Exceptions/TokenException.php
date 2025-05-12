<?php

namespace PaymentSystem\Exceptions;

use RuntimeException;

class TokenException extends RuntimeException
{
    public static function suspended(): static
    {
        return new static("Token is suspended");
    }
}