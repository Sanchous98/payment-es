<?php

namespace PaymentSystem\Gateway\Resources;

use Money\Money;

interface RefundInterface extends ResourceInterface
{
    public function getFee(): ?Money;
}