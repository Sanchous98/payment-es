<?php

namespace PaymentSystem\Events;

use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;
use PaymentSystem\ValueObjects\State;

readonly class BillingAddressUpdated
{
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $city = null,
        public ?Country $country = null,
        public ?string $postalCode = null,
        public ?Email $email = null,
        public ?PhoneNumber $phone = null,
        public ?string $addressLine = null,
        public ?string $addressLineExtra = null,
        public ?State $state = null,
    ) {
    }
}