<?php

namespace PaymentSystem\Events;

use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;
use PaymentSystem\ValueObjects\State;

readonly class BillingAddressCreated
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $city,
        public Country $country,
        public string $postalCode,
        public Email $email,
        public PhoneNumber $phone,
        public string $addressLine,
        public string $addressLineExtra = '',
        public ?State $state = null,
    ) {
    }
}