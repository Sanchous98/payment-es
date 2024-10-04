<?php

use PaymentSystem\Commands\CreatePaymentMethodCommandInterface;
use PaymentSystem\Enum\PaymentMethodStatusEnum;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodSucceeded;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\Tests\IntId;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Cash;
use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;
use PaymentSystem\ValueObjects\Source;

use function Pest\Faker\fake;

test('payment method created successfully', function () {
    $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(new IntId(1), generator());

    $command = $this->createStub(CreatePaymentMethodCommandInterface::class);
    $command->method('getBillingAddress')->willReturn(new BillingAddress(
        firstName: fake()->firstName(),
        lastName: fake()->lastName(),
        city: fake()->city(),
        country: new Country(fake()->countryCode()),
        postalCode: fake()->postcode(),
        email: new Email(fake()->email()),
        phone: new PhoneNumber(fake()->e164PhoneNumber()),
        addressLine: fake()->address(),
    ));
    $command->method('getSource')->willReturn(Source::wrap(new Cash()));

    $paymentMethod->create($command);

    expect($paymentMethod)
        ->getBillingAddress()->toEqual($command->getBillingAddress())
        ->getSource()->toEqual($command->getSource())
        ->is(PaymentMethodStatusEnum::PENDING)->toBeTrue();
});

test('payment method succeeded', function () {
    $billingAddress = new BillingAddress(
        firstName: fake()->firstName(),
        lastName: fake()->lastName(),
        city: fake()->city(),
        country: new Country(fake()->countryCode()),
        postalCode: fake()->postcode(),
        email: new Email(fake()->email()),
        phone: new PhoneNumber(fake()->e164PhoneNumber()),
        addressLine: fake()->address(),
    );
    $source = Source::wrap(new Cash());

    $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
        new IntId(1),
        generator([new PaymentMethodCreated($billingAddress, $source)])
    );

    $paymentMethod->success();

    expect($paymentMethod)
        ->getBillingAddress()->toEqual($billingAddress)
        ->getSource()->toEqual($source)
        ->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue();
});

test('payment method failed', function () {
    $billingAddress = new BillingAddress(
        firstName: fake()->firstName(),
        lastName: fake()->lastName(),
        city: fake()->city(),
        country: new Country(fake()->countryCode()),
        postalCode: fake()->postcode(),
        email: new Email(fake()->email()),
        phone: new PhoneNumber(fake()->e164PhoneNumber()),
        addressLine: fake()->address(),
    );
    $source = Source::wrap(new Cash());

    $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
        new IntId(1),
        generator([new PaymentMethodCreated($billingAddress, $source)])
    );

    $paymentMethod->fail();

    expect($paymentMethod)
        ->getBillingAddress()->toEqual($billingAddress)
        ->getSource()->toEqual($source)
        ->is(PaymentMethodStatusEnum::FAILED)->toBeTrue();
});

test('payment method suspended successfully', function () {
    $billingAddress = new BillingAddress(
        firstName: fake()->firstName(),
        lastName: fake()->lastName(),
        city: fake()->city(),
        country: new Country(fake()->countryCode()),
        postalCode: fake()->postcode(),
        email: new Email(fake()->email()),
        phone: new PhoneNumber(fake()->e164PhoneNumber()),
        addressLine: fake()->address(),
    );
    $source = Source::wrap(new Cash());

    $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
        new IntId(1),
        generator([
            new PaymentMethodCreated($billingAddress, $source),
            new PaymentMethodSucceeded()
        ])
    );

    $paymentMethod->suspend();

    expect($paymentMethod)
        ->getBillingAddress()->toEqual($billingAddress)
        ->getSource()->toEqual($source)
        ->is(PaymentMethodStatusEnum::SUSPENDED)->toBeTrue();
});