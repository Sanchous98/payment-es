<?php

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Serialization\SerializablePayload;
use Money\Currency;
use Money\Money;
use PaymentSystem\ValueObjects\GenericId;

readonly final class RefundCreated implements SerializablePayload
{
    public function __construct(
        public Money $money,
        public AggregateRootId $paymentIntentId,
    ) {
    }

    public function toPayload(): array
    {
        return [
            ...$this->money->jsonSerialize(),
            'payment_intent_id' => $this->paymentIntentId->toString(),
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            new Money($payload['amount'], new Currency($payload['currency'])),
            new GenericId($payload['payment_intent_id']),
        );
    }
}