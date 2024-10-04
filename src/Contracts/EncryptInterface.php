<?php

namespace PaymentSystem\Contracts;

interface EncryptInterface
{
    public function encrypt(string $data): string;
}