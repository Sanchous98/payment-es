<?php

declare(strict_types=1);

namespace PaymentSystem\Enum;

enum ECICodesEnum: int
{
    case MASTERCARD_FAILED = 0;
    case MASTERCARD_ATTEMPTED = 1;
    case MASTERCARD_SUCCESSFUL = 2;
    case VISA_FAILED = 7;
    case VISA_ATTEMPTED = 6;
    case VISA_SUCCESSFUL = 5;
}
