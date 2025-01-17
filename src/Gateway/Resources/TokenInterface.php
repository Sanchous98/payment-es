<?php

namespace PaymentSystem\Gateway\Resources;

use PaymentSystem\Contracts\TokenizedSourceInterface;

interface TokenInterface extends ResourceInterface
{
    public function getCard(): TokenizedSourceInterface;
}