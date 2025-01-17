<?php

namespace PaymentSystem\Gateway\Resources;

use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\ValueObjects\BillingAddress;

interface PaymentMethodInterface extends ResourceInterface
{
    public function getBillingAddress(): BillingAddress;

    public function getSource(): SourceInterface;
}