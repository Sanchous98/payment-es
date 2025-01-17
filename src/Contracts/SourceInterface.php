<?php

declare(strict_types=1);

namespace PaymentSystem\Contracts;

interface SourceInterface
{
    public const TYPE = 'unknown';

    public function isValid(): bool;
}