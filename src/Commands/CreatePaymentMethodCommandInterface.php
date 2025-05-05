<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\ThreeDSResult;

interface CreatePaymentMethodCommandInterface
{
    public function getId(): AggregateRootId;

    public function getBillingAddress(): BillingAddress;

    public function getSource(): SourceInterface;

    public function getThreeDS(): ?ThreeDSResult;
}