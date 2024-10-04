<?php

namespace PaymentSystem\ValueObjects;

use JsonSerializable;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Stringable;

readonly class PhoneNumber implements Stringable, JsonSerializable
{
    private \libphonenumber\PhoneNumber $number;

    /**
     * @throws NumberParseException
     */
    public function __construct(string $number)
    {
        $this->number = PhoneNumberUtil::getInstance()->parse($number);
    }

    public function __toString(): string
    {
        return PhoneNumberUtil::getInstance()->format($this->number, PhoneNumberFormat::E164);
    }

    public function jsonSerialize(): string
    {
        return (string)$this;
    }
}