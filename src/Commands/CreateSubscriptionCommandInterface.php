<?php

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\ValueObjects\SubscriptionPlan;

interface CreateSubscriptionCommandInterface
{
    public function getId(): AggregateRootId;

    public function getPlan(): SubscriptionPlan;

    public function getPaymentMethod(): PaymentMethodAggregateRoot;
}