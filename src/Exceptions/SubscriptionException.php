<?php

namespace PaymentSystem\Exceptions;

use PaymentSystem\Enum\SubscriptionStatusEnum;

class SubscriptionException extends \RuntimeException
{
    public static function paymentIntentNotAttached(): self
    {
        return new self('Payment intent is not attached to a subscription');
    }
    public static function paymentIntentNotAttachedToThis(): self
    {
        return new self('Payment intent is not attached to this subscription');
    }

    public static function paymentIntentNotSucceeded(): self
    {
        return new self('Payment intent is not succeeded');
    }

    public static function moneyMismatch(): self
    {
        return new self('Money mismatch');
    }

    public static function paymentIntentAlreadyUsed(): self
    {
        return new self('Payment intent already used for activation');
    }

    public static function cannotCancel(SubscriptionStatusEnum $status): self
    {
        return new self("Cannot cancel $status->value subscription");
    }
}