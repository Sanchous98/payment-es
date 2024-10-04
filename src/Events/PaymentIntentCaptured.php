<?php

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Serialization\SerializablePayload;

readonly final class PaymentIntentCaptured implements SerializablePayload
{
    public function __construct(
        public ?string $amount = null,
        public ?AggregateRootId $paymentMethodId = null,
    ) {
    }

    public function toPayload(): array
    {
        return [
            isset($this->amount) ? ['amount' => $this->amount] : [],
            isset($this->paymentMethodId) ? ['payment_method_id' => $this->paymentMethodId] : [],
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            $payload['amount'] ?? null,
            $payload['payment_method_id'] ?? null
        );
    }
}