<?php

namespace PaymentSystem\Commands;

use PaymentSystem\PaymentMethodAggregateRoot;

interface CapturePaymentCommandInterface
{
    public function getAmount(): ?string;

    public function getPaymentMethod(): ?PaymentMethodAggregateRoot;
}