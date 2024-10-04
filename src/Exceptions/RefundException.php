<?php

namespace PaymentSystem\Exceptions;

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
}