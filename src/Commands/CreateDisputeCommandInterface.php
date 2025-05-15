<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\PaymentIntentAggregateRoot;

interface CreateDisputeCommandInterface
{
    public AggregateRootId $id { get; }

    public PaymentIntentAggregateRoot $paymentIntent { get; }

    public Money $money { get; }

    public Money $fee { get; }

    public string $reason { get; }
}