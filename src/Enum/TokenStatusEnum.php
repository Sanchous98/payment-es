<?php

namespace PaymentSystem\Enum;

enum TokenStatusEnum: string
{
    case CREATED = 'created';
    case USED = 'used';
    case DECLINED = 'declined';
}
