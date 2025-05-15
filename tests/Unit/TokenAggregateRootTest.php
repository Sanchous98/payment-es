<?php

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Commands\CreateTokenCommandInterface;
use PaymentSystem\Entities\BillingAddress;
use PaymentSystem\Enum\TokenStatusEnum;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Events\TokenDeclined;
use PaymentSystem\Events\TokenUsed;
use PaymentSystem\Exceptions\CardException;
use PaymentSystem\Exceptions\TokenException;
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

        $command = new class implements CreateTokenCommandInterface
        {
            public AggregateRootId $id { get => new GenericId(1); }
            public CreditCard $card { get => new CreditCard(
                new CreditCard\Number('424242', '4242', 'visa'),
                CreditCard\Expiration::fromMonthAndYear(12, 34),
                new CreditCard\Holder('Andrea Palladio'),
                new CreditCard\Cvc(),
            );}
            public ?BillingAddress $billingAddress { get => null; }
        };

        when(fn() => TokenAggregateRoot::create($command))
            ->then(new TokenCreated($card));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(TokenAggregateRoot::class)
            ->is(TokenStatusEnum::PENDING)->toBeTrue()
            ->source->toEqual($card);
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
            ->expectToFail(CardException::expired());
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
            ->is(TokenStatusEnum::USED)->toBeTrue()
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
            ->expectToFail(TokenException::suspended());
    });

    it('cannot be used until accepted by gateway', function () {
        given(new TokenCreated(new CreditCard(
            new CreditCard\Number('424242', '4242', 'visa'),
            CreditCard\Expiration::fromMonthAndYear(12, 34),
            new CreditCard\Holder('Andrea Palladio'),
            new CreditCard\Cvc(),
        )))
            ->when(fn() => $this->retrieveAggregateRoot($this->newAggregateRootId())->use())
            ->expectToFail(TokenException::suspended());
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
            ->is(TokenStatusEnum::REVOKED)->toBeTrue()
            ->declineReason->toBe('test reason');
    });

    it('cannot decline expired token', function () {
        given(new TokenCreated(new CreditCard(
            new CreditCard\Number('424242', '4242', 'visa'),
            CreditCard\Expiration::fromMonthAndYear(12, 34),
            new CreditCard\Holder('Andrea Palladio'),
            new CreditCard\Cvc(),
        )), new GatewayTokenAdded($this->createStub(TokenInterface::class)), new TokenUsed())
            ->when(fn() => $this->retrieveAggregateRoot($this->newAggregateRootId())->decline('test reason'))
            ->expectToFail(TokenException::suspended());
    });
});