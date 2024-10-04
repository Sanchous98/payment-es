<?php

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Serialization\SerializablePayload;
use Money\Currency;
use Money\Money;
use PaymentSystem\ValueObjects\GenericId;
use PaymentSystem\ValueObjects\ThreeDSResult;

readonly final class PaymentIntentAuthorized implements SerializablePayload
{
    public function __construct(
        public Money $money,
        public ?AggregateRootId $paymentMethodId = null,
        public string $merchantDescriptor = '',
        public string $description = '',
        public ?ThreeDSResult $threeDSResult = null,
    ) {
    }

    public function toPayload(): array
    {
        return [
            ...$this->money->jsonSerialize(),
            'payment_method_id' => $this->paymentMethodId?->toString(),
            'merchant_descriptor' => $this->merchantDescriptor,
            'description' => $this->description,
            'three_ds' => $this->threeDSResult?->jsonSerialize(),
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            new Money($payload['amount'], new Currency($payload['currency'])),
            isset($payload['payment_method_id']) ? new GenericId($payload['payment_method_id']) : null,
            $payload['merchant_descriptor'],
            $payload['description'],
            isset($payload['three_ds']) ? new ThreeDSResult(...$payload['three_ds']) : null,
        );
    }
}