<?php

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;
use PaymentSystem\ValueObjects\State;

interface CreateBillingAddressCommandInterface
{
    public function getId(): AggregateRootId;

    public function getFirstName(): string;

    public function getLastName(): string;

    public function getCity(): string;

    public function getCountry(): Country;

    public function getPostalCode(): string;

    public function getEmail(): Email;

    public function getPhoneNumber(): PhoneNumber;

    public function getAddressLine(): string;

    public function getAddressLineExtra(): string;

    public function getState(): ?State;
}