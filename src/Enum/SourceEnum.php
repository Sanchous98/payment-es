<?php

namespace PaymentSystem\Enum;

enum SourceEnum: string
{
    case CARD = 'card';
    case CASH = 'cash';
    case TOKEN = 'token';
}
