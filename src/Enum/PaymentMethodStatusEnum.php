<?php

namespace PaymentSystem\Enum;

enum PaymentMethodStatusEnum: string
{
    case PENDING = 'pending';
    case FAILED = 'failed';
    case SUCCEEDED = 'success';
    case SUSPENDED = 'suspended';
}
