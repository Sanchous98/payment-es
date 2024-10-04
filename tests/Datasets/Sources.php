<?php

use PaymentSystem\Contracts\SourceInterface;

class Cash implements SourceInterface
{
    public const TYPE = 'cash';

    public function isValid(): bool
    {
        return true;
    }
}

dataset('source', function () {
    yield new Cash();
});