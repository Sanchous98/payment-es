<?php

namespace PaymentSystem\Enum;

enum PaymentMethodStatusEnum: string
{
    case PENDING = 'pending';
    case FAILED = 'failed';

    /**
     * @todo value is incorrect due to backwards compatibility. Fix in 2.0
     */
    case SUCCEEDED = 'success';
    case SUSPENDED = 'suspended';
}
