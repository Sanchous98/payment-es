<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\SubscriptionAggregateRoot;
use PaymentSystem\TenderInterface;
use PaymentSystem\ValueObjects\MerchantDescriptor;
use PaymentSystem\ValueObjects\ThreeDSResult;

interface AuthorizePaymentCommandInterface
{
    public AggregateRootId $id { get; }

    public Money $money { get; }

    public ?TenderInterface $tender { get; }

    public MerchantDescriptor $merchantDescriptor { get; }

    public string $description { get; }

    public ?ThreeDSResult $threeDSResult { get; }

    public ?SubscriptionAggregateRoot $subscription { get; }
}