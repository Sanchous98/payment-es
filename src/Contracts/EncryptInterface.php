<?php

declare(strict_types=1);

namespace PaymentSystem\Contracts;

interface EncryptInterface
{
    public function encrypt(string $data): string;
}