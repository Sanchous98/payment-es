<?php

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Commands\CreatePaymentMethodCommandInterface;
use PaymentSystem\Commands\CreateTokenPaymentMethodCommandInterface;
use PaymentSystem\Commands\UpdatedPaymentMethodCommandInterface;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Entities\BillingAddress;
use PaymentSystem\Enum\PaymentMethodStatusEnum;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodFailed;
use PaymentSystem\Events\PaymentMethodSuspended;
use PaymentSystem\Events\PaymentMethodUpdated;
use PaymentSystem\Exceptions\PaymentMethodSuspendedException;
use PaymentSystem\Exceptions\TokenExpiredException;
use PaymentSystem\Gateway\Events\GatewayPaymentMethodAdded;
use PaymentSystem\Gateway\Events\GatewayPaymentMethodSuspended;
use PaymentSystem\Gateway\Events\GatewayPaymentMethodUpdated;
use PaymentSystem\Gateway\Resources\PaymentMethodInterface;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\Tests\PaymentMethods;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\ValueObjects\CreditCard;

use function EventSauce\EventSourcing\PestTooling\given;
use function EventSauce\EventSourcing\PestTooling\when;

uses(PaymentMethods::class);

describe('domain-first flow', function () {
    it('payment method created successfully from token', function () {
        $token = $this->createStub(TokenAggregateRoot::class);
        $billingAddress = $this->createStub(BillingAddress::class);
        $token->method('getSource')->willReturn(new CreditCard(
            new CreditCard\Number('424242', '4242', 'visa'),
            CreditCard\Expiration::fromMonthAndYear(12, 34),
            new CreditCard\Holder('Andrea Palladio'),
            new CreditCard\Cvc(),
        ));
        $token->method('getBillingAddress')->willReturn($billingAddress);
        $token->method('getSource')->willReturn($token->getSource());
        $token->method('isValid')->willReturn(true);

        $command = $this->createStub(CreateTokenPaymentMethodCommandInterface::class);
        $command->method('getToken')->willReturn($token);
        $command->method('getId')->willReturn($this->aggregateRootId());

        when(fn() => PaymentMethodAggregateRoot::createFromToken($command))
            ->then(new PaymentMethodCreated($billingAddress, $token->getSource(), null, $token->aggregateRootId()));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
            ->getBillingAddress()->toEqual($billingAddress)
            ->getSource()->toEqual($token->getSource())
            ->is(PaymentMethodStatusEnum::PENDING)->toBeTrue()
            ->isValid()->toBeFalse();
    });
    it('cannot create payment method from expired token', function () {
        $token = $this->createStub(TokenAggregateRoot::class);
        $token->method('isValid')->willReturn(false);

        $command = $this->createStub(CreateTokenPaymentMethodCommandInterface::class);
        $command->method('getToken')->willReturn($token);

        when(fn() => PaymentMethodAggregateRoot::createFromToken($command))
            ->expectToFail(new TokenExpiredException());
    });
    it('payment method created successfully from source', function () {
        $card = new CreditCard(
            new CreditCard\Number('424242', '4242', 'visa'),
            CreditCard\Expiration::fromMonthAndYear(12, 34),
            new CreditCard\Holder('Andrea Palladio'),
            new CreditCard\Cvc(),
        );
        $command = $this->createStub(CreatePaymentMethodCommandInterface::class);
        $command->method('getId')->willReturn($this->aggregateRootId());
        $command->method('getSource')->willReturn($card);

        when(fn() => PaymentMethodAggregateRoot::create($command))
            ->then(new PaymentMethodCreated($command->getBillingAddress(), $card));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
            ->getBillingAddress()->toEqual($command->getBillingAddress())
            ->getSource()->toEqual($card)
            ->is(PaymentMethodStatusEnum::PENDING)->toBeTrue()
            ->isValid()->toBeFalse();
    });
    it('payment method succeeded', function () {
        $gateway = $this->createStub(PaymentMethodInterface::class);

        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
        )
            ->when(function (PaymentMethodAggregateRoot $paymentMethod) use($gateway) {
                $paymentMethod->getGatewayPaymentMethods()
                    ->add(fn() => $gateway);
                return $paymentMethod;
            })
            ->then(new GatewayPaymentMethodAdded($gateway));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
            ->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue()
            ->isValid()->toBeTrue();
    });
    it('payment method suspended successfully', function () {
        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
            new GatewayPaymentMethodAdded($this->createStub(PaymentMethodInterface::class)),
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->suspend())
            ->then(new PaymentMethodSuspended());

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
            ->is(PaymentMethodStatusEnum::SUSPENDED)->toBeTrue()
            ->isValid()->toBeFalse();
    });
    it('payment method failed', function () {
        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->fail())
            ->then(new PaymentMethodFailed());

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
            ->is(PaymentMethodStatusEnum::FAILED)->toBeTrue()
            ->isValid()->toBeFalse();
    });
    it('can be used while valid', function () {
        $gateway = $this->createStub(PaymentMethodInterface::class);

        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
            new GatewayPaymentMethodAdded($gateway),
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->use())
            ->nothingShouldHaveHappened();

        expect($this->retrieveAggregateRoot($this->aggregateRootId()));
    });
    it('cannot be used when suspended', function () {
        $gateway = $this->createStub(PaymentMethodInterface::class);

        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
            new GatewayPaymentMethodAdded($gateway),
            new PaymentMethodSuspended()
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->use())
            ->expectToFail(new PaymentMethodSuspendedException());
    });
    it('cannot be used if creation failed', function () {
        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
            new PaymentMethodFailed()
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->use())
            ->expectToFail(new PaymentMethodSuspendedException());
    });
    it('cannot be used while it\'s peniding for creation', function () {
        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->use())
            ->expectToFail(new PaymentMethodSuspendedException());
    });
    it('cannot fail when succeeded', function () {
        $gateway = $this->createStub(PaymentMethodInterface::class);

        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
            new GatewayPaymentMethodAdded($gateway),
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->fail())
            ->expectToFail(new RuntimeException('Payment method is not pending to creating'));
    });
    it('cannot suspend while pending', function () {
        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->suspend())
            ->expectToFail(new PaymentMethodSuspendedException());
    });
    it('cannot suspend failed', function () {
        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
            new PaymentMethodFailed(),
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->suspend())
            ->expectToFail(new PaymentMethodSuspendedException());
    });
    it('can update payment method while pending', function () {
        $command = $this->createStub(UpdatedPaymentMethodCommandInterface::class);
        $oldBillingAddress = $this->createStub(BillingAddress::class);

        given(
            new PaymentMethodCreated($oldBillingAddress, $this->createStub(SourceInterface::class)),
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->update($command))
            ->then(new PaymentMethodUpdated($command->getBillingAddress()));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
            ->is(PaymentMethodStatusEnum::PENDING)->toBeTrue()
            ->isValid()->toBeFalse()
            ->getBillingAddress()->toEqual($command->getBillingAddress())->not()->toEqual($oldBillingAddress);
    });
    it('can update succeeded payment method', function () {
        $command = $this->createStub(UpdatedPaymentMethodCommandInterface::class);
        $oldBillingAddress = $this->createStub(BillingAddress::class);
        $gateway = $this->createStub(PaymentMethodInterface::class);

        given(
            new PaymentMethodCreated($oldBillingAddress, $this->createStub(SourceInterface::class)),
            new GatewayPaymentMethodAdded($gateway),
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->update($command))
            ->then(new PaymentMethodUpdated($command->getBillingAddress()));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
            ->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue()
            ->isValid()->toBeTrue()
            ->getBillingAddress()->toEqual($command->getBillingAddress())->not()->toEqual($oldBillingAddress);
    });
    it('cannot updated failed', function () {
        $command = $this->createStub(UpdatedPaymentMethodCommandInterface::class);
        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
            new PaymentMethodFailed(),
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->update($command))
            ->expectToFail(new PaymentMethodSuspendedException());
    });
    it('cannot updated suspended', function () {
        $command = $this->createStub(UpdatedPaymentMethodCommandInterface::class);
        given(
            new PaymentMethodCreated($this->createStub(BillingAddress::class), $this->createStub(SourceInterface::class)),
            new PaymentMethodSuspended(),
        )
            ->when(fn(PaymentMethodAggregateRoot $paymentMethod) => $paymentMethod->update($command))
            ->expectToFail(new PaymentMethodSuspendedException());
    });
});

describe('gateway-only flow', function () {
    it('creates payment method', function () {
        $gateway = $this->createStub(PaymentMethodInterface::class);
        $gateway->method('getId')->willReturn($this->createStub(AggregateRootId::class));
        $gateway->method('getGatewayId')->willReturn($this->createStub(AggregateRootId::class));
        $gateway->method('isValid')->willReturn(true);
        $gateway->method('getBillingAddress')->willReturn($this->createStub(BillingAddress::class));
        $gateway->method('getSource')->willReturn($this->createStub(CreditCard::class));

        when(function(PaymentMethodAggregateRoot $paymentMethod) use($gateway) {
            $paymentMethod->getGatewayPaymentMethods()->add(fn() => $gateway);
            return $paymentMethod;
        })
            ->then(new GatewayPaymentMethodAdded($gateway));
    });
    it('updates payment method', function () {
        $gateway = $this->createStub(PaymentMethodInterface::class);
        $gateway->method('getId')->willReturn($this->createStub(AggregateRootId::class));
        $gateway->method('getGatewayId')->willReturn($this->createStub(AggregateRootId::class));
        $gateway->method('isValid')->willReturn(true);
        $gateway->method('getBillingAddress')->willReturn($this->createStub(BillingAddress::class));
        $gateway->method('getSource')->willReturn($this->createStub(CreditCard::class));

        given(
            new GatewayPaymentMethodAdded($gateway)
        )
            ->when(function(PaymentMethodAggregateRoot $paymentMethod) use($gateway) {
                $paymentMethod->getGatewayPaymentMethods()
                    ->update($gateway->getGatewayId(), $gateway->getId(), fn() => $gateway);
                return $paymentMethod;
            })
            ->then(new GatewayPaymentMethodUpdated($gateway));
    });
    it('does not suspend payment method if there are more available', function () {
        $gateway = $this->createStub(PaymentMethodInterface::class);
        $gateway->method('getGatewayId')->willReturn($this->createStub(AggregateRootId::class));
        $gateway->method('getBillingAddress')->willReturn($this->createStub(BillingAddress::class));
        $gateway->method('getSource')->willReturn($this->createStub(CreditCard::class));

        $gateway2 = clone $gateway;
        $gateway2->method('getId')->willReturn($this->createStub(AggregateRootId::class));
        $gateway2->method('isValid')->willReturn(true);

        $gatewaySuspended = clone $gateway;
        $gatewaySuspended->method('isValid')->willReturn(false);

        $gateway->method('getId')->willReturn($this->createStub(AggregateRootId::class));
        $gateway->method('isValid')->willReturn(true);

        given(
            new GatewayPaymentMethodAdded($gateway),
            new GatewayPaymentMethodAdded($gateway2),
        )
            ->when(function(PaymentMethodAggregateRoot $paymentMethod) use($gateway) {
                $paymentMethod->getGatewayPaymentMethods()
                    ->suspend($gateway->getGatewayId(), $gateway->getId(), fn() => $gateway);
                return $paymentMethod;
            })
            ->then(new GatewayPaymentMethodSuspended($gateway));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
            ->isValid()->toBeTrue()
            ->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue();
    });
    it('suspend if all gateway method are suspended', function () {
        $gateway = $this->createStub(PaymentMethodInterface::class);
        $gateway->method('getId')->willReturn($this->createStub(AggregateRootId::class));
        $gateway->method('getGatewayId')->willReturn($this->createStub(AggregateRootId::class));
        $gateway->method('getBillingAddress')->willReturn($this->createStub(BillingAddress::class));
        $gateway->method('getSource')->willReturn($this->createStub(CreditCard::class));

        $gatewaySuspended = clone $gateway;
        $gateway->method('isValid')->willReturn(true);
        $gatewaySuspended->method('isValid')->willReturn(false);

        given(
            new GatewayPaymentMethodAdded($gateway),
        )
            ->when(function(PaymentMethodAggregateRoot $paymentMethod) use($gatewaySuspended) {
                $paymentMethod->getGatewayPaymentMethods()
                    ->suspend($gatewaySuspended->getGatewayId(), $gatewaySuspended->getId(), fn() => $gatewaySuspended);

                return $paymentMethod;
            })
            ->then(new GatewayPaymentMethodSuspended($gatewaySuspended));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
            ->isValid()->toBeFalse()
            ->is(PaymentMethodStatusEnum::SUSPENDED)->toBeTrue();
    });
});

test('payment method is serialized and unserialized successfully', function () {
    $paymentMethod = $this->retrieveAggregateRoot($this->newAggregateRootId());
    $serialized = serialize($paymentMethod);
    /** @var PaymentMethodAggregateRoot $paymentMethod */
    $paymentMethod = unserialize($serialized);

    $gateway = $this->createStub(PaymentMethodInterface::class);
    $gateway->method('getId')->willReturn($this->createStub(AggregateRootId::class));
    $gateway->method('getGatewayId')->willReturn($this->createStub(AggregateRootId::class));
    $gateway->method('isValid')->willReturn(true);

    $paymentMethod->getGatewayPaymentMethods()->add(fn() => $gateway);
    expect($paymentMethod->releaseEvents())->toContainEqual(new GatewayPaymentMethodAdded($gateway));
});