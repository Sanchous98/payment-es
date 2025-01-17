<?php

declare(strict_types=1);

namespace PaymentSystem\Contracts;

interface DecryptInterface
{
    public function decrypt(string $data): string;
}