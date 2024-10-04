<?php

namespace PaymentSystem\ValueObjects\CreditCard;

use JsonSerializable;
use PaymentSystem\Contracts\DecryptInterface;
use PaymentSystem\Contracts\EncryptInterface;
use Stringable;

readonly class Cvc implements Stringable, JsonSerializable
{
    private string $data;

    public static function fromCvc(string $cvc, EncryptInterface $encrypter): self
    {
        $self = new self();
        $self->data = $encrypter->encrypt($cvc);

        return $self;
    }

    public function __debugInfo(): array
    {
        return [
            'data' => '***'
        ];
    }

    public function getCvc(DecryptInterface $decrypter): ?string
    {
        if (!isset($this->data)) {
            return null;
        }

        return $decrypter->decrypt($this->data);
    }

    public function __toString(): string
    {
        return '';
    }

    public function jsonSerialize(): string
    {
        return '***';
    }
}