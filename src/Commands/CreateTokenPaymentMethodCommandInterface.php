<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\TokenAggregateRoot;

interface CreateTokenPaymentMethodCommandInterface
{
    public AggregateRootId $id { get; }

    public TokenAggregateRoot $token { get; }
}