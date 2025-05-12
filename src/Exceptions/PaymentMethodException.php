<?php

namespace PaymentSystem\Exceptions;

use RuntimeException;

class PaymentMethodException extends RuntimeException
{
    public static function suspended()
    {
        return new static("Payment method is suspended");
    }
}