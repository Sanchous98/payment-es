<?php

use EventSauce\EventSourcing\AggregateRootId;
use Money\Currency;
use Money\Money;
use PaymentSystem\Commands\AuthorizePaymentCommandInterface;
use PaymentSystem\Commands\CapturePaymentCommandInterface;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Events\PaymentIntentAuthorized;
use PaymentSystem\Events\PaymentIntentCanceled;
use PaymentSystem\Events\PaymentIntentCaptured;
use PaymentSystem\Events\PaymentIntentDeclined;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodFailed;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Events\TokenUsed;
use PaymentSystem\Exceptions\CancelUnavailableException;
use PaymentSystem\Exceptions\CaptureUnavailableException;
use PaymentSystem\Exceptions\DeclineUnavailableException;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\Exceptions\PaymentMethodSuspendedException;
use PaymentSystem\Exceptions\TokenExpiredException;
use PaymentSystem\Gateway\Events\GatewayPaymentIntentAuthorized;
use PaymentSystem\Gateway\Events\GatewayPaymentIntentCanceled;
use PaymentSystem\Gateway\Events\GatewayPaymentIntentCaptured;
use PaymentSystem\Gateway\Resources\PaymentIntentInterface;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\Tests\PaymentIntents;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\CreditCard;
use PaymentSystem\ValueObjects\GenericId;
use PaymentSystem\ValueObjects\MerchantDescriptor;
use PaymentSystem\ValueObjects\ThreeDSResult;

use function EventSauce\EventSourcing\PestTooling\given;
use function EventSauce\EventSourcing\PestTooling\when;

uses(PaymentIntents::class);

describe('domain-first flow', function () {
    it('is authorized successfully without payment method', function () {
        $threeDS = $this->createStub(ThreeDSResult::class);
        $command = $this->createStub(AuthorizePaymentCommandInterface::class);
        $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
        $command->method('getDescription')->willReturn('test description');
        $command->method('getMerchantDescriptor')->willReturn(new MerchantDescriptor('test', ' merchant descriptor'));
        $command->method('getThreeDSResult')->willReturn($threeDS);
        $command->method('getSubscription')->willReturn(null);

        when(fn() => PaymentIntentAggregateRoot::authorize($command))
            ->then(new PaymentIntentAuthorized(
                $command->getMoney(),
                $command->getTender()?->aggregateRootId(),
                $command->getMerchantDescriptor(),
                $command->getDescription(),
                $command->getThreeDSResult(),
            ));

        expect($this->retrieveAggregateRoot($command->getId()))
            ->toBeInstanceOf(PaymentIntentAggregateRoot::class)
            ->getMoney()->equals(new Money(100, new Currency('USD')))->toBeTrue()
            ->getDescription()->toBe('test description')
            ->getMerchantDescriptor()->toEqual(new MerchantDescriptor('test', ' merchant descriptor'))
            ->getThreeDSResult()->toBe($threeDS)
            ->is(PaymentIntentStatusEnum::REQUIRES_PAYMENT_METHOD)->toBeTrue()
            ->getStatus()->toBe(PaymentIntentStatusEnum::REQUIRES_PAYMENT_METHOD);
    })->wip('can we really do this even with stripe?');
    it('is authorized successfully on payment method', function () {
        $threeDS = $this->createStub(ThreeDSResult::class);
        $command = $this->createStub(AuthorizePaymentCommandInterface::class);
        $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
        $command->method('getDescription')->willReturn('test description');
        $command->method('getMerchantDescriptor')->willReturn(new MerchantDescriptor('test', ' merchant descriptor'));
        $command->method('getThreeDSResult')->willReturn($threeDS);
        $command->method('getSubscription')->willReturn(null);

        $paymentMethod = $this->createStub(PaymentMethodAggregateRoot::class);
        $paymentMethod->method('isValid')->willReturn(true);
        $command->method('getTender')->willReturn($paymentMethod);

        when(fn() => PaymentIntentAggregateRoot::authorize($command))
            ->then(new PaymentIntentAuthorized(
                $command->getMoney(),
                $command->getTender()?->aggregateRootId(),
                $command->getMerchantDescriptor(),
                $command->getDescription(),
                $command->getThreeDSResult(),
            ));

        expect($this->retrieveAggregateRoot($command->getId()))
            ->toBeInstanceOf(PaymentIntentAggregateRoot::class)
            ->getMoney()->equals(new Money(100, new Currency('USD')))->toBeTrue()
            ->getDescription()->toBe('test description')
            ->getMerchantDescriptor()->toEqual(new MerchantDescriptor('test', ' merchant descriptor'))
            ->getThreeDSResult()->toBe($threeDS)
            ->is(PaymentIntentStatusEnum::REQUIRES_CAPTURE)->toBeTrue()
            ->getStatus()->toBe(PaymentIntentStatusEnum::REQUIRES_CAPTURE)
            ->getTenderId()->toEqual($command->getTender()?->aggregateRootId());
    });
    it('is authorized successfully on token', function () {
        $threeDS = $this->createStub(ThreeDSResult::class);
        $command = $this->createStub(AuthorizePaymentCommandInterface::class);
        $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
        $command->method('getDescription')->willReturn('test description');
        $command->method('getMerchantDescriptor')->willReturn(new MerchantDescriptor('test', ' merchant descriptor'));
        $command->method('getThreeDSResult')->willReturn($threeDS);
        $command->method('getSubscription')->willReturn(null);

        $token = $this->createStub(TokenAggregateRoot::class);
        $token->method('isValid')->willReturn(true);
        $command->method('getTender')->willReturn($token);

        when(fn() => PaymentIntentAggregateRoot::authorize($command))
            ->then(new PaymentIntentAuthorized(
                $command->getMoney(),
                $command->getTender()?->aggregateRootId(),
                $command->getMerchantDescriptor(),
                $command->getDescription(),
                $command->getThreeDSResult(),
            ));

        expect($this->retrieveAggregateRoot($command->getId()))
            ->toBeInstanceOf(PaymentIntentAggregateRoot::class)
            ->getMoney()->equals(new Money(100, new Currency('USD')))->toBeTrue()
            ->getDescription()->toBe('test description')
            ->getMerchantDescriptor()->toEqual(new MerchantDescriptor('test', ' merchant descriptor'))
            ->getThreeDSResult()->toBe($threeDS)
            ->is(PaymentIntentStatusEnum::REQUIRES_CAPTURE)->toBeTrue()
            ->getStatus()->toBe(PaymentIntentStatusEnum::REQUIRES_CAPTURE)
            ->getTenderId()->toEqual($command->getTender()?->aggregateRootId());
    });
    it('cannot authorize negative amount', function () {
        $command = $this->createStub(AuthorizePaymentCommandInterface::class);
        $command->method('getMoney')->willReturn(new Money(-100, new Currency('USD')));

        when(fn() => PaymentIntentAggregateRoot::authorize($command))
            ->expectToFail(InvalidAmountException::notNegative());
    });
    it('cannot authorize 0 amount', function () {
        $command = $this->createStub(AuthorizePaymentCommandInterface::class);
        $command->method('getMoney')->willReturn(new Money(0, new Currency('USD')));

        when(fn() => PaymentIntentAggregateRoot::authorize($command))
            ->expectToFail(InvalidAmountException::notZero());
    });
    it('cannot authorize not valid payment method', function () {
        $command = $this->createStub(AuthorizePaymentCommandInterface::class);
        $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));

        $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(new GenericId(2), (function () {
            yield new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class));
            yield new PaymentMethodFailed();
        })());

        $command->method('getTender')->willReturn($paymentMethod);

        when(fn() => PaymentIntentAggregateRoot::authorize($command))
            ->expectToFail(new PaymentMethodSuspendedException());
    });
    it('cannot authorize not valid token', function () {
        $command = $this->createStub(AuthorizePaymentCommandInterface::class);
        $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
        $token = TokenAggregateRoot::reconstituteFromEvents(new GenericId(2), (function () {
            yield new TokenCreated($this->createStub(CreditCard::class));
            yield new TokenUsed();
        })());
        $command->method('getTender')->willReturn($token);

        when(fn() => PaymentIntentAggregateRoot::authorize($command))
            ->expectToFail(new TokenExpiredException());
    });

    it('payment captured successfully', function () {
        given(new PaymentIntentAuthorized(
            new Money(100, new Currency('USD')),
            $this->createStub(AggregateRootId::class),
            new MerchantDescriptor('test', ' merchant descriptor'),
            'test description',
        ))->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->capture($this->createStub(CapturePaymentCommandInterface::class)))
            ->then(new PaymentIntentCaptured());

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentIntentAggregateRoot::class)
            ->getMoney()->equals(new Money(100, new Currency('USD')))->toBeTrue()
            ->getDescription()->toBe('test description')
            ->getMerchantDescriptor()->toEqual(new MerchantDescriptor('test', ' merchant descriptor'))
            ->is(PaymentIntentStatusEnum::SUCCEEDED)->toBeTrue()
            ->getStatus()->toBe(PaymentIntentStatusEnum::SUCCEEDED)
            ->getAuthAndCaptureDifference()->equals(new Money(0, new Currency('USD')));
    });
    it('payment captured partially', function () {
        $command = $this->createStub(CapturePaymentCommandInterface::class);
        $command->method('getAmount')->willReturn('50');

        given(new PaymentIntentAuthorized(
            new Money(100, new Currency('USD')),
            $this->createStub(AggregateRootId::class),
            new MerchantDescriptor('test', ' merchant descriptor'),
            'test description',
        ))->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->capture($command))
            ->then(new PaymentIntentCaptured($command->getAmount()));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentIntentAggregateRoot::class)
            ->getMoney()->equals(new Money(50, new Currency('USD')))->toBeTrue()
            ->getDescription()->toBe('test description')
            ->getMerchantDescriptor()->toEqual(new MerchantDescriptor('test', ' merchant descriptor'))
            ->is(PaymentIntentStatusEnum::SUCCEEDED)->toBeTrue()
            ->getStatus()->toBe(PaymentIntentStatusEnum::SUCCEEDED)
            ->getAuthAndCaptureDifference()->equals(new Money(50, new Currency('USD')));
    });
    it('cannot capture negative amount', function () {
        $command = $this->createStub(CapturePaymentCommandInterface::class);
        $command->method('getAmount')->willReturn('-50');

        given(new PaymentIntentAuthorized(
            new Money(100, new Currency('USD')),
            $this->createStub(AggregateRootId::class),
        ))
            ->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->capture($command))
            ->expectToFail(InvalidAmountException::notNegative());
    });
    it('cannot capture 0 amount', function () {
        $command = $this->createStub(CapturePaymentCommandInterface::class);
        $command->method('getAmount')->willReturn('0');

        given(new PaymentIntentAuthorized(
            new Money(100, new Currency('USD')),
            $this->createStub(AggregateRootId::class),
        ))
            ->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->capture($command))
            ->expectToFail(InvalidAmountException::notZero());
    });
    it('cannot capture more than authorized', function () {
        $command = $this->createStub(CapturePaymentCommandInterface::class);
        $command->method('getAmount')->willReturn('200');

        given(new PaymentIntentAuthorized(
            new Money(100, new Currency('USD')),
            $this->createStub(AggregateRootId::class),
        ))
            ->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->capture($command))
            ->expectToFail(InvalidAmountException::notGreaterThanAuthorized('100'));
    });
    it('cannot capture without tender if it was not provided while authorization', function () {
        $command = $this->createStub(CapturePaymentCommandInterface::class);
        $command->method('getAmount')->willReturn('100');
        $command->method('getTender')->willReturn(null);

        given(new PaymentIntentAuthorized(new Money(100, new Currency('USD'))))
            ->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->capture($command))
            ->expectToFail(CaptureUnavailableException::paymentMethodIsRequired());
    });
    it('cannot capture captured payment', function () {
        given(
            new PaymentIntentAuthorized(new Money(100, new Currency('USD'))),
            new PaymentIntentCaptured(tenderId: $this->createStub(AggregateRootId::class)),
        )
            ->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->capture($this->createStub(CapturePaymentCommandInterface::class)))
            ->expectToFail(CaptureUnavailableException::unsupportedIntentStatus(PaymentIntentStatusEnum::SUCCEEDED));
    });
    it('cannot capture declined payment', function () {
        given(
            new PaymentIntentAuthorized(new Money(100, new Currency('USD'))),
            new PaymentIntentDeclined('test reason'),
        )
            ->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->capture($this->createStub(CapturePaymentCommandInterface::class)))
            ->expectToFail(CaptureUnavailableException::unsupportedIntentStatus(PaymentIntentStatusEnum::DECLINED));
    });
    it('cannot capture canceled payment', function () {
        given(
            new PaymentIntentAuthorized(new Money(100, new Currency('USD'))),
            new PaymentIntentCanceled(),
        )
            ->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->capture($this->createStub(CapturePaymentCommandInterface::class)))
            ->expectToFail(CaptureUnavailableException::unsupportedIntentStatus(PaymentIntentStatusEnum::CANCELED));
    });

    it('payment canceled successfully', function () {
        given(new PaymentIntentAuthorized(
            new Money(100, new Currency('USD')),
            $this->createStub(AggregateRootId::class),
        ))->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->cancel())
            ->then(new PaymentIntentCanceled());

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentIntentAggregateRoot::class)
            ->is(PaymentIntentStatusEnum::CANCELED)->toBeTrue();
    });
    it('cannot cancel captured payment', function () {
        given(
            new PaymentIntentAuthorized(
                new Money(100, new Currency('USD')),
                $this->createStub(AggregateRootId::class),
            ),
            new PaymentIntentCaptured(),
        )->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->cancel())
            ->expectToFail(CancelUnavailableException::unsupportedIntentStatus(PaymentIntentStatusEnum::SUCCEEDED));
    });
    it('cannot cancel canceled payment', function () {
        given(
            new PaymentIntentAuthorized(
                new Money(100, new Currency('USD')),
                $this->createStub(AggregateRootId::class),
            ),
            new PaymentIntentCanceled(),
        )->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->cancel())
            ->expectToFail(CancelUnavailableException::unsupportedIntentStatus(PaymentIntentStatusEnum::CANCELED));
    });
    it('cannot cancel declined payment', function () {
        given(
            new PaymentIntentAuthorized(
                new Money(100, new Currency('USD')),
                $this->createStub(AggregateRootId::class),
            ),
            new PaymentIntentCaptured(),
            new PaymentIntentDeclined('test reason'),
        )->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->cancel())
            ->expectToFail(CancelUnavailableException::unsupportedIntentStatus(PaymentIntentStatusEnum::DECLINED));
    });

    it('payment declined successfully', function () {
        given(
            new PaymentIntentAuthorized(
                new Money(100, new Currency('USD')),
                $this->createStub(AggregateRootId::class),
            ),
        )->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->decline('test reason'))
            ->then(new PaymentIntentDeclined('test reason'));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentIntentAggregateRoot::class)
            ->is(PaymentIntentStatusEnum::DECLINED)->toBeTrue()
            ->getDeclineReason()->toBe('test reason');
    });
    it('cannot decline canceled payment', function () {
        given(
            new PaymentIntentAuthorized(
                new Money(100, new Currency('USD')),
                $this->createStub(AggregateRootId::class),
            ),
            new PaymentIntentCanceled(),
        )->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->decline('test reason'))
            ->expectToFail(DeclineUnavailableException::unsupportedIntentStatus(PaymentIntentStatusEnum::CANCELED));
    });
    it('cannot decline declined payment', function () {
        given(
            new PaymentIntentAuthorized(
                new Money(100, new Currency('USD')),
                $this->createStub(AggregateRootId::class),
            ),
            new PaymentIntentCaptured(),
            new PaymentIntentDeclined('test reason'),
        )->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->decline('test reason 2'))
            ->expectToFail(DeclineUnavailableException::unsupportedIntentStatus(PaymentIntentStatusEnum::DECLINED));
    });
    it('cannot decline captured payment', function () {
        given(
            new PaymentIntentAuthorized(
                new Money(100, new Currency('USD')),
                $this->createStub(AggregateRootId::class),
            ),
            new PaymentIntentCaptured()
        )->when(fn(PaymentIntentAggregateRoot $paymentIntent) => $paymentIntent->decline('test reason'))
            ->expectToFail(DeclineUnavailableException::unsupportedIntentStatus(PaymentIntentStatusEnum::SUCCEEDED));
    });
});

describe('gateway-only flow', function () {
    it('can authorize', function () {
        $gateway = $this->createStub(PaymentIntentInterface::class);
        $gateway->method('getMoney')->willReturn(new Money(100, new Currency('USD')));

        when(function (PaymentIntentAggregateRoot $paymentIntent) use($gateway) {
            $paymentIntent->getGatewayPaymentIntent()->authorize(fn() => $gateway);
            return $paymentIntent;
        })
            ->then(new GatewayPaymentIntentAuthorized($gateway));
    });
    it('can capture', function () {
        $gateway = $this->createStub(PaymentIntentInterface::class);
        $gateway->method('getMoney')->willReturn(new Money(100, new Currency('USD')));

        $captured = clone $gateway;
        $captured->method('getPaymentMethodId')->willReturn($this->createStub(AggregateRootId::class));

        given(
            new GatewayPaymentIntentAuthorized($gateway),
        )
            ->when(function (PaymentIntentAggregateRoot $paymentIntent) use($captured) {
                $paymentIntent->getGatewayPaymentIntent()->capture(fn() => $captured);
                return $paymentIntent;
            })
            ->then(new GatewayPaymentIntentCaptured($captured));
    });
    it('can cancel', function () {
        $gateway = $this->createStub(PaymentIntentInterface::class);
        $gateway->method('getMoney')->willReturn(new Money(100, new Currency('USD')));

        $canceled = clone $gateway;

        given(
            new GatewayPaymentIntentAuthorized($gateway),
        )
            ->when(function (PaymentIntentAggregateRoot $paymentIntent) use($canceled) {
                $paymentIntent->getGatewayPaymentIntent()->cancel(fn() => $canceled);
                return $paymentIntent;
            })
            ->then(new GatewayPaymentIntentCanceled($canceled));
    });
});

test('payment intent is serialized and unserialized successfully', function () {
    $paymentIntent = $this->retrieveAggregateRoot($this->newAggregateRootId());
    $serialized = serialize($paymentIntent);
    /** @var PaymentIntentAggregateRoot $paymentIntent */
    $paymentIntent = unserialize($serialized);

    $gateway = $this->createStub(PaymentIntentInterface::class);
    $gateway->method('getId')->willReturn($this->createStub(AggregateRootId::class));
    $gateway->method('getGatewayId')->willReturn($this->createStub(AggregateRootId::class));
    $gateway->method('isValid')->willReturn(true);
    $gateway->method('getMoney')->willReturn(new Money(100, new Currency('USD')));

    $paymentIntent->getGatewayPaymentIntent()->authorize(fn() => $gateway);
    expect($paymentIntent->releaseEvents())->toContainEqual(new GatewayPaymentIntentAuthorized($gateway));
});