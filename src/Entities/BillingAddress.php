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
        private readonly AggregateRootId $id,
        public string $firstName {
            get => $this->firstName;
        },
        public string $lastName {
            get => $this->lastName;
        },
        public string $city {
            get => $this->city;
        },
        public Country $country {
            get => $this->country;
        },
        public string $postalCode {
            get => $this->postalCode;
        },
        public Email $email {
            get => $this->email;
        },
        public PhoneNumber $phone {
            get => $this->phone;
        },
        public string $addressLine {
            get => $this->addressLine;
        },
        public string $addressLineExtra = '' {
            get => $this->addressLineExtra;
        },
        public ?State $state = null {
            get => $this->state;
        },
    ) {
    }

    public function getId(): AggregateRootId
    {
        return $this->id;
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