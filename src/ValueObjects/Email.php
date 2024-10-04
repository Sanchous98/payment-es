<?php

namespace PaymentSystem\ValueObjects;

use JsonSerializable;
use RuntimeException;
use Stringable;

readonly class Email implements Stringable, JsonSerializable
{
    public function __construct(private string $email)
    {
        $this->validate();
    }

    private function validate(): void
    {
        filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false || throw new RuntimeException('Invalid email');
    }

    public function __toString(): string
    {
        return $this->email;
    }

    public function jsonSerialize(): string
    {
        return (string)$this;
    }
}