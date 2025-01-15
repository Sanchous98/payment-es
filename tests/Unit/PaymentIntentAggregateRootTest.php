<?php

use Money\Currency;
use Money\Money;
use PaymentSystem\Commands\AuthorizePaymentCommandInterface;
use PaymentSystem\Commands\CapturePaymentCommandInterface;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodFailed;
use PaymentSystem\Events\PaymentMethodSucceeded;
use PaymentSystem\Events\PaymentMethodSuspended;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Exceptions\CancelUnavailableException;
use PaymentSystem\Exceptions\DeclineUnavailableException;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\Exceptions\PaymentMethodSuspendedException;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\Tests\IntId;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\CreditCard;

describe('payment authorize', function () {
    test(
        'payment is authorized successfully by payment method',
        function (BillingAddress $billingAddress, SourceInterface $source) {
            $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
                new IntId(1),
                generator([
                    new PaymentMethodCreated($billingAddress, $source),
                    new PaymentMethodSucceeded()
                ])
            );

            $command = $this->createStub(AuthorizePaymentCommandInterface::class);
            $command->method('getId')->willReturn(new IntId(3));
            $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
            $command->method('getTender')->willReturn($paymentMethod);
            $command->method('getMerchantDescriptor')->willReturn('');

            $paymentIntent = PaymentIntentAggregateRoot::authorize($command);

            expect($paymentIntent)
                ->getMoney()->equals(new Money(100, new Currency('USD')))->toBeTrue()
                ->is(PaymentIntentStatusEnum::REQUIRES_CAPTURE)->toBeTrue()
                ->getMerchantDescriptor()->toBe('');
        }
    )->with('billing address', 'source');

    test('payment is authorized successfully by token', function () {
        $token = TokenAggregateRoot::reconstituteFromEvents(
            new IntId(1),
            generator([
                new TokenCreated(
                    new CreditCard(
                        new CreditCard\Number('424242', '4242', 'visa'),
                        new CreditCard\Expiration(12, 34),
                        new CreditCard\Holder('Andrea Palladio'),
                        new CreditCard\Cvc(),
                    )
                )
            ])
        );

        $command = $this->createStub(AuthorizePaymentCommandInterface::class);
        $command->method('getId')->willReturn(new IntId(3));
        $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
        $command->method('getTender')->willReturn($token);
        $command->method('getMerchantDescriptor')->willReturn('');

        $paymentIntent = PaymentIntentAggregateRoot::authorize($command);

        expect($paymentIntent)
            ->getMoney()->equals(new Money(100, new Currency('USD')))->toBeTrue()
            ->is(PaymentIntentStatusEnum::REQUIRES_CAPTURE)->toBeTrue()
            ->getMerchantDescriptor()->toBe('');
    });

    test('authorize negative amount', function (BillingAddress $billingAddress, SourceInterface $source) {
        $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
            new IntId(1),
            generator([
                new PaymentMethodCreated($billingAddress, $source),
                new PaymentMethodSucceeded()
            ])
        );

        $paymentIntent = PaymentIntentAggregateRoot::reconstituteFromEvents(new IntId(1), generator());

        $command = $this->createStub(AuthorizePaymentCommandInterface::class);
        $command->method('getMoney')->willReturn(new Money(-100, new Currency('USD')));
        $command->method('getTender')->willReturn($paymentMethod);
        $command->method('getMerchantDescriptor')->willReturn('');

        $paymentIntent->authorize($command);
    })->with('billing address', 'source')->throws(InvalidAmountException::class);

    test('authorize 0 amount', function (BillingAddress $billingAddress, SourceInterface $source) {
        $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
            new IntId(1),
            generator([
                new PaymentMethodCreated($billingAddress, $source),
                new PaymentMethodSucceeded()
            ])
        );

        $paymentIntent = PaymentIntentAggregateRoot::reconstituteFromEvents(new IntId(1), generator());

        $command = $this->createStub(AuthorizePaymentCommandInterface::class);
        $command->method('getMoney')->willReturn(new Money(0, new Currency('USD')));
        $command->method('getTender')->willReturn($paymentMethod);
        $command->method('getMerchantDescriptor')->willReturn('');

        $paymentIntent->authorize($command);
    })->with('billing address', 'source')->throws(InvalidAmountException::class);

    test(
        'cannot authorize on suspended payment method',
        function (BillingAddress $billingAddress, SourceInterface $source) {
            $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
                new IntId(1),
                generator([
                    new PaymentMethodCreated($billingAddress, $source),
                    new PaymentMethodSucceeded(),
                    new PaymentMethodSuspended(),
                ])
            );

            $paymentIntent = PaymentIntentAggregateRoot::reconstituteFromEvents(new IntId(1), generator());

            $command = $this->createStub(AuthorizePaymentCommandInterface::class);
            $command->method('getMoney')->willReturn(new Money(0, new Currency('USD')));
            $command->method('getTender')->willReturn($paymentMethod);
            $command->method('getMerchantDescriptor')->willReturn('');

            $paymentIntent->authorize($command);
        }
    )->with('billing address', 'source')->throws(PaymentMethodSuspendedException::class);

    test(
        'cannot authorize on failed payment method',
        function (BillingAddress $billingAddress, SourceInterface $source) {
            $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
                new IntId(1),
                generator([
                    new PaymentMethodCreated($billingAddress, $source),
                    new PaymentMethodFailed(),
                ])
            );

            $paymentIntent = PaymentIntentAggregateRoot::reconstituteFromEvents(new IntId(1), generator());

            $command = $this->createStub(AuthorizePaymentCommandInterface::class);
            $command->method('getMoney')->willReturn(new Money(0, new Currency('USD')));
            $command->method('getTender')->willReturn($paymentMethod);
            $command->method('getMerchantDescriptor')->willReturn('');

            $paymentIntent->authorize($command);
        }
    )->with('billing address', 'source')->throws(PaymentMethodSuspendedException::class);

    test(
        'cannot authorize on not approved payment method',
        function (BillingAddress $billingAddress, SourceInterface $source) {
            $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
                new IntId(1),
                generator([
                    new PaymentMethodCreated($billingAddress, $source),
                ])
            );

            $paymentIntent = PaymentIntentAggregateRoot::reconstituteFromEvents(new IntId(1), generator());

            $command = $this->createStub(AuthorizePaymentCommandInterface::class);
            $command->method('getMoney')->willReturn(new Money(0, new Currency('USD')));
            $command->method('getTender')->willReturn($paymentMethod);
            $command->method('getMerchantDescriptor')->willReturn('');

            $paymentIntent->authorize($command);
        }
    )->with('billing address', 'source')->throws(PaymentMethodSuspendedException::class);
});

describe('payment capture', function () {
    test('payment captured successfully', function (PaymentIntentAggregateRoot $paymentIntent) {
        $command = $this->createStub(CapturePaymentCommandInterface::class);
        $command->method('getTender')->willReturn(null);
        $command->method('getAmount')->willReturn(null);

        $paymentIntent->capture($command);

        expect($paymentIntent)
            ->getMoney()->equals(new Money(100, new Currency('USD')))->toBeTrue()
            ->is(PaymentIntentStatusEnum::SUCCEEDED)->toBeTrue()
            ->getMerchantDescriptor()->toBe('');
    })->with('authorized payment');

    test('payment captured partially', function (PaymentIntentAggregateRoot $paymentIntent) {
        $command = $this->createStub(CapturePaymentCommandInterface::class);
        $command->method('getTender')->willReturn(null);
        $command->method('getAmount')->willReturn('50');

        $paymentIntent->capture($command);

        expect($paymentIntent)
            ->getMoney()->equals(new Money(50, new Currency('USD')))->toBeTrue()
            ->is(PaymentIntentStatusEnum::SUCCEEDED)->toBeTrue()
            ->getMerchantDescriptor()->toBe('');
    })->with('authorized payment');

    test('capture negative amount', function (PaymentIntentAggregateRoot $paymentIntent) {
        $command = $this->createStub(CapturePaymentCommandInterface::class);
        $command->method('getTender')->willReturn(null);
        $command->method('getAmount')->willReturn('-100');

        $paymentIntent->capture($command);
    })->with('authorized payment')->throws(InvalidAmountException::class);

    test('capture 0 amount', function (PaymentIntentAggregateRoot $paymentIntent) {
        $command = $this->createStub(CapturePaymentCommandInterface::class);
        $command->method('getTender')->willReturn(null);
        $command->method('getAmount')->willReturn('0');

        $paymentIntent->capture($command);
    })->with('authorized payment')->throws(InvalidAmountException::class);
});

describe('payment cancel', function () {
    test('payment canceled successfully', function (PaymentIntentAggregateRoot $paymentIntent) {
        $paymentIntent->cancel();

        expect($paymentIntent)->is(PaymentIntentStatusEnum::CANCELED)->toBeTrue();
    })->with('authorized payment');

    test('cannot cancel captured payment', function (PaymentIntentAggregateRoot $paymentIntent) {
        $paymentIntent->cancel();
    })->with('captured payment')->throws(CancelUnavailableException::class);

    test('cannot cancel canceled payment', function (PaymentIntentAggregateRoot $paymentIntent) {
        $paymentIntent->cancel();
    })->with('canceled payment')->throws(CancelUnavailableException::class);
});

describe('payment declined', function () {
    test('payment declined successfully', function (PaymentIntentAggregateRoot $paymentIntent) {
        $paymentIntent->decline('test reason');

        expect($paymentIntent)
            ->is(PaymentIntentStatusEnum::DECLINED)->toBeTrue()
            ->getDeclineReason()->toBe('test reason');
    })->with('authorized payment');

    test('cannot cancel captured payment', function (PaymentIntentAggregateRoot $paymentIntent) {
        $paymentIntent->decline('');
    })->with('captured payment')->throws(DeclineUnavailableException::class);
});