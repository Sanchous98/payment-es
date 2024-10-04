<?php

namespace PaymentSystem\ValueObjects;

use PaymentSystem\Enum\SourceEnum;

readonly class Cash implements SourceInterface
{
    public function getType(): SourceEnum
    {
        return SourceEnum::CASH;
    }

    public function jsonSerialize(): null
    {
        return null;
    }
}