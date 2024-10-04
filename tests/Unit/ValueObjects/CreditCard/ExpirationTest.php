<?php

use PaymentSystem\ValueObjects\CreditCard\Expiration;

it('accepts date in future', function () {
    $date = new \DateTime('+1 month');
    new Expiration($date->format('m'), $date->format('y'));
})->throwsNoExceptions();

it('does not accept date in past', function () {
    $date = new \DateTime('-1 month');
    new Expiration($date->format('m'), $date->format('y'));
})->throws(RuntimeException::class);

it('accepts date in present', function () {
    $date = new \DateTime('now');
    new Expiration($date->format('m'), $date->format('y'));
})->throwsNoExceptions();