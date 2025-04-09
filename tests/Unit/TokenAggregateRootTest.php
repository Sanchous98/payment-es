<?php

use PaymentSystem\Commands\CreateTokenCommandInterface;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Events\TokenDeclined;
use PaymentSystem\Events\TokenUsed;
use PaymentSystem\Exceptions\CardExpiredException;
use PaymentSystem\Exceptions\TokenExpiredException;
use PaymentSystem\Gateway\Events\GatewayTokenAdded;
use PaymentSystem\Gateway\Resources\TokenInterface;
use PaymentSystem\Tests;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\ValueObjects\CreditCard;
use PaymentSystem\ValueObjects\GenericId;

use function EventSauce\EventSourcing\PestTooling\given;
use function EventSauce\EventSourcing\PestTooling\when;

uses(Tests\Tokens::class);

describe('domain-first flow', function () {
    it('creates token', function () {
        $card = new CreditCard(
            new CreditCard\Number('424242', '4242', 'visa'),
            CreditCard\Expiration::fromMonthAndYear(12, 34),
            new CreditCard\Holder('Andrea Palladio'),
            new CreditCard\Cvc(),
        );
        $command = $this->createStub(CreateTokenCommandInterface::class);
        $command->method('getId')->willReturn($this->newAggregateRootId());
        $command->method('getBillingAddress')->willReturn(null);
        $command->method('getCard')->willReturn($card);

        when(fn() => TokenAggregateRoot::create($command))
            ->then(new TokenCreated($card));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(TokenAggregateRoot::class)
            ->isPending()->toBeTrue()
            ->getSource()->toBe($card);
    });

    it('does not accept expired cards', function () {
        $expiration = new DateTimeImmutable('-1 month');

        $command = $this->createStub(CreateTokenCommandInterface::class);
        $command->method('getId')->willReturn($this->newAggregateRootId());
        $command->method('getCard')->willReturn(
            new CreditCard(
                new CreditCard\Number('424242', '4242', 'visa'),
                new CreditCard\Expiration($expiration),
                new CreditCard\Holder('Andrea Palladio'),
                new CreditCard\Cvc(),
            )
        );

        when(fn() => TokenAggregateRoot::create($command))
            ->expectToFail(new CardExpiredException());
    });

    it('can be used once', function () {
        given(new TokenCreated(new CreditCard(
            new CreditCard\Number('424242', '4242', 'visa'),
            CreditCard\Expiration::fromMonthAndYear(12, 34),
            new CreditCard\Holder('Andrea Palladio'),
            new CreditCard\Cvc(),
        )), new GatewayTokenAdded($gateway = $this->createStub(TokenInterface::class)))
            ->when(fn(TokenAggregateRoot $token) => $token->use())
            ->then(new TokenUsed());

        expect($this->retrieveAggregateRoot($this->newAggregateRootId()))
            ->toBeInstanceOf(TokenAggregateRoot::class)
            ->isUsed()->toBeTrue()
            ->getGatewayTenders()->toContain($gateway);
    });

    it('cannot be used twice', function () {
        given(
            new TokenCreated(new CreditCard(
                new CreditCard\Number('424242', '4242', 'visa'),
                CreditCard\Expiration::fromMonthAndYear(12, 34),
                new CreditCard\Holder('Andrea Palladio'),
                new CreditCard\Cvc(),
            )),
            new GatewayTokenAdded($this->createStub(TokenInterface::class)),
            new TokenUsed()
        )
            ->when(fn(TokenAggregateRoot $token) => $token->use())
            ->expectToFail(new TokenExpiredException());
    });

    it('cannot be used until accepted by gateway', function () {
        given(new TokenCreated(new CreditCard(
            new CreditCard\Number('424242', '4242', 'visa'),
            CreditCard\Expiration::fromMonthAndYear(12, 34),
            new CreditCard\Holder('Andrea Palladio'),
            new CreditCard\Cvc(),
        )))
            ->when(fn() => $this->retrieveAggregateRoot($this->newAggregateRootId())->use())
            ->expectToFail(new TokenExpiredException());
    });

    it('can be declined', function () {
        given(new TokenCreated(new CreditCard(
            new CreditCard\Number('424242', '4242', 'visa'),
            CreditCard\Expiration::fromMonthAndYear(12, 34),
            new CreditCard\Holder('Andrea Palladio'),
            new CreditCard\Cvc(),
        )), new GatewayTokenAdded($this->createStub(TokenInterface::class)))
            ->when(fn() => $this->retrieveAggregateRoot($this->newAggregateRootId())->decline('test reason'))
            ->then(new TokenDeclined('test reason'));

        expect($this->retrieveAggregateRoot($this->newAggregateRootId()))
            ->isDeclined()->toBeTrue()
            ->getDeclineReason()->toBe('test reason');
    });

    it('cannot decline expired token', function () {
        given(new TokenCreated(new CreditCard(
            new CreditCard\Number('424242', '4242', 'visa'),
            CreditCard\Expiration::fromMonthAndYear(12, 34),
            new CreditCard\Holder('Andrea Palladio'),
            new CreditCard\Cvc(),
        )), new GatewayTokenAdded($this->createStub(TokenInterface::class)), new TokenUsed())
            ->when(fn() => $this->retrieveAggregateRoot($this->newAggregateRootId())->decline('test reason'))
            ->expectToFail(new TokenExpiredException());
    });
});

describe('gateway-only flow', function () {
    it('creates token', function () {
        $gateway = $this->createStub(TokenInterface::class);
        $gateway->method('getId')->willReturn(new GenericId('testId'));
        $gateway->method('getSource')->willReturn(new CreditCard(
            new CreditCard\Number('424242', '4242', 'amex'),
            CreditCard\Expiration::fromMonthAndYear(12, 34),
            new CreditCard\Holder('ANDREA PALLADIO'),
            new CreditCard\Cvc(),
        ));

        when(function(TokenAggregateRoot $token) use ($gateway) {
            $token->getGatewayTokens()->add(fn() => $gateway);
            return $token;
        })->then(new GatewayTokenAdded($gateway));

        expect($this->repository->retrieve($this->aggregateRootId()))
            ->toBeInstanceOf(TokenAggregateRoot::class)
            ->aggregateRootId()->toBe($this->aggregateRootId())
            ->isUsed()->toBeFalse()
            ->isValid()->toBeTrue()
            ->getSource()->toEqual(new CreditCard(
                new CreditCard\Number('424242', '4242', 'amex'),
                CreditCard\Expiration::fromMonthAndYear(12, 34),
                new CreditCard\Holder('ANDREA PALLADIO'),
                new CreditCard\Cvc(),
            ));
    });
});

test('token is serialized and unserialized successfully', function () {
    $token = $this->retrieveAggregateRoot($this->newAggregateRootId());
    $serialized = serialize($token);
    /** @var TokenAggregateRoot $token */
    $token = unserialize($serialized);

    $gateway = $this->createStub(TokenInterface::class);
    $gateway->method('getId')->willReturn(new GenericId('testId'));
    $gateway->method('getSource')->willReturn(new CreditCard(
        new CreditCard\Number('424242', '4242', 'amex'),
        CreditCard\Expiration::fromMonthAndYear(12, 34),
        new CreditCard\Holder('ANDREA PALLADIO'),
        new CreditCard\Cvc(),
    ));


    $token->getGatewayTokens()->add(fn() => $gateway);
    expect($token->releaseEvents())->toContainEqual(new GatewayTokenAdded($gateway));
});