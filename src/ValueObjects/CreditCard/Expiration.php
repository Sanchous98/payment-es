<?php

namespace PaymentSystem\ValueObjects\CreditCard;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

readonly class Expiration implements JsonSerializable
{
    private DateTimeImmutable $expiration;

    public function __construct(DateTimeInterface $expiration)
    {
        $this->expiration = DateTimeImmutable::createFromInterface($expiration);
    }

    public static function fromMonthAndYear(int $month, int $year): Expiration
    {
        if ($year < 100) {
            $expiration = DateTimeImmutable::createFromFormat("ny", sprintf("%d%02d", $month, $year));
        } else {
            $expiration = DateTimeImmutable::createFromFormat("nY", "$month $year");
        }

        return new self($expiration);
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
        return $this->format('ny');
    }

    public function jsonSerialize(): string
    {
        return (string)$this;
    }
}