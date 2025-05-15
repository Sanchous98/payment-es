<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Entities\BillingAddress;
use PaymentSystem\ValueObjects\ThreeDSResult;

interface CreatePaymentMethodCommandInterface
{
    public AggregateRootId $id { get; }

    public BillingAddress $billingAddress { get; }

    public SourceInterface $source { get; }

    public ?ThreeDSResult $threeDS { get; }
}