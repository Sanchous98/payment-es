<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\PaymentIntentAggregateRoot;

interface CreateRefundCommandInterface
{
    public AggregateRootId $id { get; }

    public PaymentIntentAggregateRoot $paymentIntent { get; }

    public Money $money { get; }
}