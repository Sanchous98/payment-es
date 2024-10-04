<?php

namespace PaymentSystem\ValueObjects;

use PaymentSystem\Enum\SourceEnum;

readonly class Token implements SourceInterface
{
    public function __construct(
        public string $tokenId,
        public ?string $customerId = null,
        public array $metadata = [],
    ) {
    }

    public function getType(): SourceEnum
    {
        return SourceEnum::TOKEN;
    }

    public function jsonSerialize(): array
    {
        return [
            'token_id' => $this->tokenId,
            'customer_id' => $this->customerId,
            ...$this->metadata,
        ];
    }
}