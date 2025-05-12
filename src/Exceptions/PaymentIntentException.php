<?php

namespace PaymentSystem\Exceptions;

use PaymentSystem\Enum\PaymentIntentStatusEnum;
use RuntimeException;

class PaymentIntentException extends RuntimeException
{
    public static function unsupportedIntentDeclineStatus(PaymentIntentStatusEnum $enum): static
    {
        return new static("Cannot decline intent with status '$enum->value'");
    }

    public static function unsupportedIntentCancelStatus(PaymentIntentStatusEnum $enum): static
    {
        return new static("Cannot cancel intent with status '$enum->value'");
    }
    public static function unsupportedIntentCaptureStatus(PaymentIntentStatusEnum $enum): static
    {
        return new static("Cannot capture intent with status '$enum->value'");
    }

    public static function paymentMethodIsRequired(): static
    {
        return new static("Cannot capture this intent. Payment method is required");
    }
}