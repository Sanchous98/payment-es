<?php

namespace PaymentSystem\ValueObjects;

use JsonSerializable;
use RuntimeException;
use Stringable;
use Symfony\Component\Intl\Countries;

readonly class Country implements Stringable, JsonSerializable
{
    private string $country;

    public function __construct(string $country)
    {
        if (strlen($country) === 3) {
            if (is_numeric($country)) {
                $this->country = Countries::getAlpha2FromNumeric($country);
                return;
            }

            $country = Countries::getAlpha2Code($country);
        }

        $this->country = $country;

        $this->validate();
    }

    private function validate(): void
    {
        Countries::exists($this->country) || throw new RuntimeException('Country is not valid');
    }

    public function __toString(): string
    {
        return $this->country;
    }

    public function jsonSerialize(): string
    {
        return (string)$this;
    }

    public function getAlpha3(): string
    {
        return Countries::getAlpha3Code($this->country);
    }
}