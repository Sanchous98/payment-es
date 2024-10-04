<?php

namespace PaymentSystem\Commands;

use Money\Money;
use PaymentSystem\PaymentIntentAggregateRoot;

interface CreateRefundCommandInterface
{
    public function getPaymentIntent(): PaymentIntentAggregateRoot;

    public function getMoney(): Money;
}