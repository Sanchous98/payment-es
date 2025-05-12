<?php

declare(strict_types=1);

namespace PaymentSystem\Exceptions;

use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Enum\RefundStatusEnum;
use RuntimeException;

class RefundException extends RuntimeException
{
    public static function cannotSucceed(RefundStatusEnum $status): static
    {
        return new static("Cannot decline $status->value refund");
    }

    public static function cannotCancel(RefundStatusEnum $status): static
    {
        return new static("Cannot cancel $status->value refund");
    }

    public static function cannotDecline(RefundStatusEnum $status): static
    {
        return new static("Cannot succeed $status->value refund");
    }

    public static function unsupportedIntentStatus(PaymentIntentStatusEnum $status): static
    {
        return new static("Cannot refund intent with status '$status->value'");
    }
}