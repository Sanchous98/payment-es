<?php

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\ValueObjects\BillingAddress;

interface CreatePaymentMethodCommandInterface
{
    public function getId(): AggregateRootId;

    public function getBillingAddress(): BillingAddress;

    public function getSource();
}