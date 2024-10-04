<?php

namespace PaymentSystem\Enum;

enum ECICodesEnum: string
{
    case MASTERCARD_FAILED = '00';
    case MASTERCARD_ATTEMPTED = '01';
    case MASTERCARD_SUCCESSFUL = '02';
    case VISA_FAILED = '07';
    case VISA_ATTEMPTED = '06';
    case VISA_SUCCESSFUL = '05';
}
