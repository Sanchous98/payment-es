<?php

declare(strict_types=1);

namespace PaymentSystem\ValueObjects;

use JsonSerializable;
use Stringable;

readonly class MerchantDescriptor implements Stringable, JsonSerializable
{
    public function __construct(
        public string $prefix = '',
        public string $suffix = '',
    ) {
        assert(strlen($this->prefix . $this->suffix) <= 25);
    }

    public function __toString(): string
    {
        return $this->prefix . $this->suffix;
    }

    public function jsonSerialize(): string
    {
        return (string)$this;
    }
}