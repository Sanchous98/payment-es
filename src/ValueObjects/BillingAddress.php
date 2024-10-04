<?php

namespace PaymentSystem\ValueObjects;

use JsonSerializable;

readonly class BillingAddress implements JsonSerializable
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

    public function jsonSerialize(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
            'email' => $this->email,
            'phone' => $this->phone,
            'address_line' => $this->addressLine,
            ...(empty($this->addressLineExtra) ? [] : ['address_line_extra' => $this->addressLineExtra]),
            ...(isset($this->state) ? ['state' => $this->state] : []),
        ];
    }
}