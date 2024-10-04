<?php

namespace PaymentSystem\Enum;

enum PaymentIntentStatusEnum: string
{
    case CREATED = 'created';

    case REQUIRES_PAYMENT_METHOD = 'requires_payment_method';

    case REQUIRES_CAPTURE = 'requires_capture';

    case SUCCEEDED = 'succeeded';

    case DECLINED = 'declined';

    case CANCELED = 'canceled';

    case ACTION_REQUIRED = 'action_required';

    case ERROR = 'error';
}
