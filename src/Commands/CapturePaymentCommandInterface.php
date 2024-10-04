<?php

namespace PaymentSystem\Commands;

use PaymentSystem\TenderInterface;

interface CapturePaymentCommandInterface
{
    public function getAmount(): ?string;

    public function getTender(): ?TenderInterface;
}