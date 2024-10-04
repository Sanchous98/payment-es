<?php

namespace PaymentSystem\ValueObjects\CreditCard;

use JsonSerializable;
use Stringable;

readonly class Holder implements Stringable, JsonSerializable
{
    public function __construct(private string $name)
    {
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function jsonSerialize(): string
    {
        return (string)$this;
    }
}