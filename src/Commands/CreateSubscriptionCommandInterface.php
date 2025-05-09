<?php

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Entities\SubscriptionPlan;
use PaymentSystem\PaymentMethodAggregateRoot;

interface CreateSubscriptionCommandInterface
{
    public function getId(): AggregateRootId;

    public function getPlan(): SubscriptionPlan;

    public function getPaymentMethod(): PaymentMethodAggregateRoot;
}