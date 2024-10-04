<?php

namespace PaymentSystem\Tests;


use EventSauce\EventSourcing\AggregateRootId;

readonly class IntId implements AggregateRootId
{
    public function __construct(private int $id)
    {
    }

    public static function fromString(string $aggregateRootId): static
    {
        return new static((int)$aggregateRootId);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return (string)$this->id;
    }
}