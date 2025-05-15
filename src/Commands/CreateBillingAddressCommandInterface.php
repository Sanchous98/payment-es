<?php

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;
use PaymentSystem\ValueObjects\State;

interface CreateBillingAddressCommandInterface
{
    public AggregateRootId $id { get; }

    public string $firstName { get; }

    public string $lastName { get; }

    public string $city { get; }

    public Country $country { get; }

    public string $postalCode { get; }

    public Email $email { get; }

    public PhoneNumber $phoneNumber { get; }

    public string $addressLine { get; }

    public string $addressLineExtra { get; }

    public ?State $state { get; }
}