<?php

namespace PaymentSystem\Enum;

enum TokenStatusEnum: string
{
    case CREATED = 'created';
    case VALID = 'valid';
    case USED = 'used';
    case DECLINED = 'declined';
}
