<?php

declare(strict_types=1);

namespace PaymentSystem\Enum;

enum PaymentIntentStatusEnum: string
{
    case CREATED = 'created';

    case REQUIRES_PAYMENT_METHOD = 'requires_payment_method';

    case REQUIRES_CAPTURE = 'requires_capture';

    case REQUIRES_ACTION = 'action_required';

    case SUCCEEDED = 'succeeded';

    case DECLINED = 'declined';

    case CANCELED = 'canceled';

    case ERROR = 'error';
}
