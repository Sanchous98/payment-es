<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Contracts\TokenizableSourceInterface;
use PaymentSystem\Entities\BillingAddress;

interface CreateTokenCommandInterface
{
    public AggregateRootId $id { get; }

    public TokenizableSourceInterface $source { get; }

    public ?BillingAddress $billingAddress { get; }
}