<?php

namespace PaymentSystem\Exceptions;

use PaymentSystem\Enum\PaymentIntentStatusEnum;
use RuntimeException;

class DeclineUnavailableException extends RuntimeException
{
    public static function unsupportedIntentStatus(PaymentIntentStatusEnum $enum): static
    {
        return new static("Cannot decline intent with status '$enum->value'");
    }
}
