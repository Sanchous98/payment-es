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
    public function getId(): AggregateRootId;

    public function getMoney(): Money;

    public function getTender(): ?TenderInterface;

    public function getMerchantDescriptor(): MerchantDescriptor;

    public function getDescription(): string;

    public function getThreeDSResult(): ?ThreeDSResult;

    public function getSubscription(): ?SubscriptionAggregateRoot;
}