<?php

namespace PaymentSystem\ValueObjects;

use DateTimeImmutable;
use PaymentSystem\Contracts\TokenizedSourceInterface;
use PaymentSystem\ValueObjects\CreditCard\Cvc;
use PaymentSystem\ValueObjects\CreditCard\Expiration;
use PaymentSystem\ValueObjects\CreditCard\Holder;
use PaymentSystem\ValueObjects\CreditCard\Number;

readonly class CreditCard implements TokenizedSourceInterface
{
    public const TYPE = 'card';

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

    public function expired(): bool
    {
        return $this->expiration->expired();
    }

    public function isValid(): bool
    {
        return !$this->expired();
    }
}