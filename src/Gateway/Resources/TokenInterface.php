<?php

namespace PaymentSystem\Gateway\Resources;

use PaymentSystem\Contracts\TokenizedSourceInterface;
use PaymentSystem\ValueObjects\BillingAddress;

interface TokenInterface extends ResourceInterface
{
    public function getSource(): TokenizedSourceInterface;

    public function getBillingAddress(): ?BillingAddress;
}