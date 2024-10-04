<?php

namespace PaymentSystem\Contracts;

interface DecryptInterface
{
    public function decrypt(string $data): string;
}