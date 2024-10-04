<?php

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\PaymentIntentAggregateRoot;

interface CreateRefundCommandInterface
{
    public function getId(): AggregateRootId;

    public function getPaymentIntent(): PaymentIntentAggregateRoot;

    public function getMoney(): Money;
}