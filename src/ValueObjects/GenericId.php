<?php

declare(strict_types=1);

namespace PaymentSystem\ValueObjects;

use EventSauce\EventSourcing\AggregateRootId;
use JsonSerializable;

readonly class GenericId implements AggregateRootId, JsonSerializable
{
    public function __construct(
        private mixed $value,
    ) {
    }

    public static function fromString(string $aggregateRootId): static
    {
        return new static($aggregateRootId);
    }

    public function jsonSerialize(): string
    {
        return (string)$this;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return (string)$this->value;
    }
}