<?php

use PaymentSystem\Commands\CreatePaymentMethodCommandInterface;
use PaymentSystem\Commands\CreateTokenPaymentMethodCommandInterface;
use PaymentSystem\Enum\PaymentMethodStatusEnum;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodSucceeded;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Events\TokenUsed;
use PaymentSystem\Exceptions\TokenExpiredException;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\Tests\IntId;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\CreditCard;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;

use function Pest\Faker\fake;

test('payment method created successfully from token', function () {
    $card = new CreditCard(
        new CreditCard\Number('424242', '4242', 'visa'),
        new CreditCard\Expiration(12, 34),
        new CreditCard\Holder('Andrea Palladio'),
        new CreditCard\Cvc(),
    );

    $token = TokenAggregateRoot::reconstituteFromEvents(new IntId(1), generator([
        new TokenCreated($card)
    ]));

    $command = $this->createStub(CreateTokenPaymentMethodCommandInterface::class);
    $command->method('getId')->willReturn(new IntId(1));
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
    $command->method('getToken')->willReturn($token);

    $paymentMethod = PaymentMethodAggregateRoot::createFromToken($command);

    expect($paymentMethod)
        ->getBillingAddress()->toEqual($command->getBillingAddress())
        ->getSource()->toEqual($command->getToken()->getCard())
        ->is(PaymentMethodStatusEnum::PENDING)->toBeTrue();
});

test('cannot create payment method from expired token', function () {
    $card = new CreditCard(
        new CreditCard\Number('424242', '4242', 'visa'),
        new CreditCard\Expiration(12, 34),
        new CreditCard\Holder('Andrea Palladio'),
        new CreditCard\Cvc(),
    );

    $token = TokenAggregateRoot::reconstituteFromEvents(new IntId(1), generator([
        new TokenCreated($card),
        new TokenUsed(),
    ]));

    $command = $this->createStub(CreateTokenPaymentMethodCommandInterface::class);
    $command->method('getId')->willReturn(new IntId(1));
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
    $command->method('getToken')->willReturn($token);

    PaymentMethodAggregateRoot::createFromToken($command);
})->throws(TokenExpiredException::class);

test('payment method created successfully', function () {
    $command = $this->createStub(CreatePaymentMethodCommandInterface::class);
    $command->method('getId')->willReturn(new IntId(1));
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
    $command->method('getSource')->willReturn(new Cash());

    $paymentMethod = PaymentMethodAggregateRoot::create($command);

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
    $source = new Cash();

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
    $source = new Cash();

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
    $source = new Cash();

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