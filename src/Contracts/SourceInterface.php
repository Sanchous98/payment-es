<?php

namespace PaymentSystem\Contracts;

interface SourceInterface
{
    public const TYPE = 'unknown';

    public function isValid(): bool;
}