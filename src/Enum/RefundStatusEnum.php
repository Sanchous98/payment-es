<?php

declare(strict_types=1);

namespace PaymentSystem\Enum;

enum RefundStatusEnum: string
{
    case CREATED = 'created';
    case REQUIRES_ACTION = 'requires_action';
    case SUCCEEDED = 'succeeded';
    case CANCELED = 'canceled';
    case DECLINED = 'declined';
}
