<?php

namespace PaymentSystem\ValueObjects;

use JsonSerializable;
use RuntimeException;

readonly class State implements JsonSerializable
{
    public function __construct(
        private string $state,
        Country $country = null,
    ) {
        if ($country === null) {
            return;
        }

        $states = require __DIR__ . '/../Resources/states.php';

        isset($states[(string)$country]) || throw new RuntimeException('Invalid state');
    }

    public function __toString(): string
    {
        return $this->state;
    }

    public function jsonSerialize(): string
    {
        return (string)$this;
    }
}