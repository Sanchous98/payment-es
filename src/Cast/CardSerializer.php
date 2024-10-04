<?php

namespace PaymentSystem\Cast;

use Attribute;
use EventSauce\ObjectHydrator\ObjectMapper;
use EventSauce\ObjectHydrator\PropertyCaster;
use EventSauce\ObjectHydrator\PropertySerializer;
use PaymentSystem\ValueObjects\CreditCard;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class CardSerializer implements PropertyCaster, PropertySerializer
{
    public function cast(mixed $value, ObjectMapper $hydrator): CreditCard
    {
        $number = isset($value['number']) ? ['number' => $value['number']] : [
            'first6' => $value['first6'],
            'last4' => $value['last4'],
            'brand' => $value['brand'],
        ];

        return new CreditCard(
            number: new CreditCard\Number(...$number),
            expiration: new CreditCard\Expiration(),
            holder: new CreditCard\Holder($value['holder']),
            cvc: new CreditCard\CVC(),
        );
    }

    public function serialize(mixed $value, ObjectMapper $hydrator): mixed
    {
        if (!$value instanceof CreditCard) {
            throw new \InvalidArgumentException('Value must be an instance of CreditCard');
        }

        return [
            ...$value->number->jsonSerialize(),
            'holder' => (string)$value->holder,
            'expiration' => (string)$value->expiration,
            'cvc' => (string)$value->cvc,
        ];
    }
}