<?php

namespace PaymentSystem\ValueObjects;

use DateTimeImmutable;
use PaymentSystem\Enum\SourceEnum;
use PaymentSystem\ValueObjects\CreditCard\Cvc;
use PaymentSystem\ValueObjects\CreditCard\Expiration;
use PaymentSystem\ValueObjects\CreditCard\Holder;
use PaymentSystem\ValueObjects\CreditCard\Number;

readonly class CreditCard implements SourceInterface
{
    public function __construct(
        public Number $number,
        public Expiration $expiration,
        public Holder $holder,
        public Cvc $cvc,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $expiration = DateTimeImmutable::createFromFormat('n y', $data['expiration']);

        return new self(
            new Number($data['first6'], $data['last4'], $data['brand']),
            new Expiration($expiration->format('n'), $expiration->format('y')),
            new Holder($data['holder']),
            new Cvc(),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            ...$this->number->jsonSerialize(),
            'expiration' => $this->expiration,
            'holder' => $this->holder,
            'cvc' => (string)$this->cvc,
        ];
    }

    public function getType(): SourceEnum
    {
        return SourceEnum::CARD;
    }

    public function expired(): bool
    {
        return $this->expiration->expired();
    }
}