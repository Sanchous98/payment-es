<?php

namespace PaymentSystem\ValueObjects\CreditCard;

use DateTimeImmutable;
use JsonSerializable;

readonly class Expiration implements JsonSerializable
{
    private DateTimeImmutable $expiration;

    public function __construct(int $month, int $year)
    {
        if ($year < 100) {
            $this->expiration = DateTimeImmutable::createFromFormat("n y", sprintf("%d %02d", $month, $year));
        } else {
            $this->expiration = DateTimeImmutable::createFromFormat("n Y", "$month $year");
        }
    }

    public function expired(): bool
    {
        return new DateTimeImmutable() > $this->expiration->modify('+1 month');
    }

    public function format(string $format): string
    {
        return $this->expiration->format($format);
    }

    public function __toString(): string
    {
        return $this->format('n y');
    }

    public function jsonSerialize(): string
    {
        return (string)$this;
    }
}