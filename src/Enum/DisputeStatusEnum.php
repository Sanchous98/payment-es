<?php

declare(strict_types=1);

namespace PaymentSystem\Enum;

enum DisputeStatusEnum: string
{
    case CREATED = 'created';
    case WON = 'won';
    case LOST = 'lost';
    case EXPIRED = 'expired';
}
