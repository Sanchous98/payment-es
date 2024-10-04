<?php

namespace PaymentSystem\ValueObjects\CreditCard;

use JsonSerializable;
use PaymentSystem\Contracts\DecryptInterface;
use PaymentSystem\Contracts\EncryptInterface;
use RuntimeException;
use Stringable;

readonly class Number implements Stringable, JsonSerializable
{
    private const PATTERNS = [
        'amex' => '/^3[47][0-9]/',
        'dankort' => '/^5019/',
        'dinersclub' => '/^3(0[0-5]|[68][0-9])[0-9]/',
        'discover' => '/^6(011|22126|22925|4[4-9]|5)/',
        'forbrugsforeningen' => '/^600/',
        'hipercard' => '/^(606282\d{10}(\d{3})?)|(3841\d{15})/',
        'jcb' => '/^(?:2131|1800|35\d{3})/',
        'maestro' => '/^(5(018|0[235]|[678])|6(1|39|7|8|9))/',
        'mastercard' => '/^(5[0-5]|2(2(2[1-9]|[3-9])|[3-6]|7(0|1|20)))/',
        'mir' => '/^220/',
        'troy' => '/^9(?!(79200|79289))/',
        'unionpay' => '/^62(?!(2126|2925))/',
        'visa' => '/^4/',
        'visaelectron' => '/^4(026|17500|405|508|844|91[37])/',
    ];

    private ?string $number;

    public function __construct(
        public string $first6,
        public string $last4,
        public string $brand,
    ) {
    }

    public static function fromNumber(string $number, EncryptInterface $encrypter): Number
    {
        $first6 = substr($number, 0, 6);
        $last4 = substr($number, -4);
        $brand = self::findBrand($number);

        $self = new self($first6, $last4, $brand);
        $self->number = $encrypter->encrypt($number);

        return $self;
    }

    private static function findBrand(string $number): string
    {
        foreach (self::PATTERNS as $name => $pattern) {
            if (preg_match($pattern, $number) !== false) {
                return $name;
            }
        }

        throw new RuntimeException('Invalid number');
    }

    public function getNumber(DecryptInterface $decrypt): ?string
    {
        if ($this->number === null) {
            return null;
        }

        return $decrypt->decrypt($this->number);
    }

    public function __debugInfo(): array
    {
        return [
            'first6' => $this->first6,
            'last4' => $this->last4,
            'brand' => $this->brand,
        ];
    }

    public function __toString(): string
    {
        return $this->first6 . $this->last4;
    }

    public function jsonSerialize(): array
    {
        return [
            'brand' => $this->brand,
            'first6' => $this->first6,
            'last4' => $this->last4,
        ];
    }
}