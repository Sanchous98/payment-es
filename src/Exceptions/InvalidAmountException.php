<?php

namespace PaymentSystem\Exceptions;

use InvalidArgumentException;

class InvalidAmountException extends InvalidArgumentException
{
    public static function notNegative(): static
    {
        return new static("Amount should not be negative");
    }

    public static function notZero(): static
    {
        return new static("Amount should not be zero");
    }

    public static function notGreaterThanAuthorized(string $amount): static
    {
        return new static("Amount should not be greater than authorized. Authorized amount: $amount");
    }

    public static function notGreaterThanCaptured(string $amount): static
    {
        return new static("Amount should not be greater than captured. Captured amount: $amount");
    }
}
