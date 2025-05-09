<?php

namespace PaymentSystem\Gateway\Resources;

use PaymentSystem\Contracts\TokenizedSourceInterface;
use PaymentSystem\Entities\BillingAddress;

interface TokenInterface extends ResourceInterface
{
    public function getSource(): TokenizedSourceInterface;

    public function getBillingAddress(): ?BillingAddress;
}