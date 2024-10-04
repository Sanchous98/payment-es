<?php

namespace PaymentSystem\Exceptions;

use PaymentSystem\Enum\PaymentIntentStatusEnum;
use RuntimeException;

class CaptureUnavailableException extends RuntimeException
{
    public static function unsupportedIntentStatus(PaymentIntentStatusEnum $enum): static
    {
        return new static("Cannot capture intent with status '$enum->value'");
    }

    public static function paymentMethodIsRequired(): static
    {
        return new static("Cannot capture this intent. Payment method is required");
    }
}
