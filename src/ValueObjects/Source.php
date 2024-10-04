<?php

namespace PaymentSystem\ValueObjects;

use PaymentSystem\Enum\SourceEnum;

readonly class Source implements SourceInterface
{
    private SourceInterface $source;

    public static function wrap(SourceInterface $source): self
    {
        if ($source instanceof self) {
            return $source;
        }

        $self = new static();
        $self->source = $source;
        return $self;
    }

    public static function fromArray(SourceEnum $type, ?array $data): self
    {
        return self::wrap(
            match ($type) {
                SourceEnum::CASH => new Cash(),
                SourceEnum::CARD => CreditCard::fromArray($data),
                SourceEnum::TOKEN => new Token($data['token_id'], $data['customer_id'] ?? null),
            }
        );
    }

    public static function factory(SourceEnum $type, ...$data): self
    {
        return self::wrap(
            match ($type) {
                SourceEnum::CASH => new Cash(),
                SourceEnum::CARD => new CreditCard(...$data),
                SourceEnum::TOKEN => new Token(...$data),
            }
        );
    }

    public function unwrap(): SourceInterface
    {
        return $this->source;
    }

    public function getType(): SourceEnum
    {
        return $this->source->getType();
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->source->getType()->value,
            $this->source->getType()->value => $this->source->jsonSerialize(),
        ];
    }
}