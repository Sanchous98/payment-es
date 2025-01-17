<?php

declare(strict_types=1);

namespace PaymentSystem\Enum;

enum TokenStatusEnum: string
{
    case PENDING = 'pending';
    case VALID = 'valid';
    case USED = 'used';
    case REVOKED = 'revoked';
    case DECLINED = 'declined';
}
