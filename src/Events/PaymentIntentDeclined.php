<?php

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\Serialization\SerializablePayload;

readonly final class PaymentIntentDeclined implements SerializablePayload
{
    public function __construct(public string $reason)
    {
    }

    public function toPayload(): array
    {
        return ['reason' => $this->reason];
    }

    public static function fromPayload(array $payload): static
    {
        return new self($payload['reason']);
    }
}