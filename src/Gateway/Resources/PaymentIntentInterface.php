<?php

namespace PaymentSystem\Gateway\Resources;

use Money\Money;

interface PaymentIntentInterface extends ResourceInterface
{
    public function getFee(): ?Money;
}