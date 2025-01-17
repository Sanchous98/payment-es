<?php

declare(strict_types=1);

namespace PaymentSystem\Exceptions;

use RuntimeException;

class DisputeException extends RuntimeException
{
    public static function cannotDisputeOnNotSucceededPayment(): static
    {
        return new static("Cannot dispute on not succeeded payment");
    }
}