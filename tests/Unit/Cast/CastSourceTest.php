<?php

use EventSauce\ObjectHydrator\ObjectMapperUsingReflection;
use PaymentSystem\Cast\CastSource;
use PaymentSystem\ValueObjects\CreditCard;

test('', function () {
    $cast = new CastSource([
        'card' => CreditCard::class,
    ]);
    $serialized = $cast->cast([
        'type' => 'card',
        'card' => [
            'number' => '4242424242424242',
            'expiration' => '12 34',
            'cvc' => '123',
            'holder' => 'John Doe',
        ]
    ], new ObjectMapperUsingReflection());
});