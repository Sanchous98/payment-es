<?php

namespace PaymentSystem\Exceptions;

use PaymentSystem\Enum\PaymentIntentStatusEnum;
use RuntimeException;

class CancelUnavailableException extends RuntimeException
{
    public static function unsupportedIntentStatus(PaymentIntentStatusEnum $enum): static
    {
        return new static("Cannot cancel intent with status '$enum->value'");
    }
}
