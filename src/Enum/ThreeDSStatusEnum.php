<?php

declare(strict_types=1);

namespace PaymentSystem\Enum;

enum ThreeDSStatusEnum: string
{
    case SUCCESSFUL = 'Y';
    case NOT_AVAILABLE = 'A';
    case NOT_AUTHENTICATED = 'N';
    case NOT_PERFORMED = 'U';
    case CHALLENGE_REQUIRED = 'C';
    case REJECTED = 'R';
}
