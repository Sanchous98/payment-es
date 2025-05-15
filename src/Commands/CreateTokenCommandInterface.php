<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Entities\BillingAddress;
use PaymentSystem\ValueObjects\CreditCard;

interface CreateTokenCommandInterface
{
    public AggregateRootId $id { get; }

    public CreditCard $card { get; }

    public ?BillingAddress $billingAddress { get; }
}