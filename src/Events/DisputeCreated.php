<?php

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Serialization\SerializablePayload;
use Money\Currency;
use Money\Money;
use PaymentSystem\ValueObjects\GenericId;

readonly final class DisputeCreated implements SerializablePayload
{
    public function __construct(
        public AggregateRootId $paymentIntentId,
        public Money $money,
        public Money $fee,
        public string $reason,
    ) {
    }

    public function toPayload(): array
    {
        return [
            'payment_intent_id' => $this->paymentIntentId->toString(),
            ...$this->money->jsonSerialize(),
            ...array_map(
                fn(string $key, $value) => ["fee_$key" => $value],
                array_keys($this->fee->jsonSerialize()),
                $this->fee->jsonSerialize(),
            ),
            'reason' => $this->reason,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            new GenericId($payload['payment_intent_id']),
            new Money($payload['amount'], new Currency($payload['currency'])),
            new Money($payload['fee_amount'], new Currency($payload['fee_currency'])),
            $payload['reason'],
        );
    }
}