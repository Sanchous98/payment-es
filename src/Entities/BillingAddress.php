<?php

declare(strict_types=1);

namespace PaymentSystem\Entities;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;
use PaymentSystem\ValueObjects\State;

class BillingAddress
{
    public function __construct(
        public readonly AggregateRootId $id,
        private(set) string $firstName,
        private(set) string $lastName,
        private(set) string $city,
        private(set) Country $country,
        private(set) string $postalCode,
        private(set) Email $email,
        private(set) PhoneNumber $phone,
        private(set) string $addressLine,
        private(set) string $addressLineExtra = '',
        private(set) ?State $state = null,
    ) {
    }

    public function update(
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $city = null,
        ?Country $country = null,
        ?string $postalCode = null,
        ?Email $email = null,
        ?PhoneNumber $phone = null,
        ?string $addressLine = null,
        ?string $addressLineExtra = null,
        ?State $state = null
    ): void {
        $this->firstName = $firstName ?? $this->firstName;
        $this->lastName = $lastName ?? $this->lastName;
        $this->city = $city ?? $this->city;
        $this->country = $country ?? $this->country;
        $this->postalCode = $postalCode ?? $this->postalCode;
        $this->email = $email ?? $this->email;
        $this->phone = $phone ?? $this->phone;
        $this->addressLine = $addressLine ?? $this->addressLine;
        $this->addressLineExtra = $addressLineExtra ?? $this->addressLineExtra;
        $this->state = $state ?? $this->state;
    }
}