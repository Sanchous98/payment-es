<?php

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\PaymentIntentAggregateRoot;

interface CreateDisputeCommandInterface
{
    public function getId(): AggregateRootId;

    public function getPaymentIntent(): PaymentIntentAggregateRoot;

    public function getMoney(): Money;

    public function getFee(): Money;

    public function getReason(): string;
}