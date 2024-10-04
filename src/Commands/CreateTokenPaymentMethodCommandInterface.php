<?php

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\ValueObjects\BillingAddress;

interface CreateTokenPaymentMethodCommandInterface
{
    public function getId(): AggregateRootId;

    public function getBillingAddress(): BillingAddress;

    public function getToken(): TokenAggregateRoot;
}