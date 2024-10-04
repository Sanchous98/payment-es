<?php

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\TenderInterface;
use PaymentSystem\ValueObjects\ThreeDSResult;

interface AuthorizePaymentCommandInterface
{
    public function getId(): AggregateRootId;

    public function getMoney(): Money;

    public function getTender(): ?TenderInterface;

    public function getMerchantDescriptor(): string;

    public function getDescription(): string;

    public function getThreeDSResult(): ?ThreeDSResult;
}