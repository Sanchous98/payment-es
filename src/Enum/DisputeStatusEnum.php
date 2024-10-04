<?php

namespace PaymentSystem\Enum;

enum DisputeStatusEnum: string
{
    case CREATED = 'created';
    case WON = 'won';
    case LOST = 'lost';
    case EXPIRED = 'expired';
}
