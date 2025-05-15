<?php

use EventSauce\EventSourcing\AggregateRootId;
use Money\Currency;
use Money\Money;
use PaymentSystem\Commands\AuthorizePaymentCommandInterface;
use PaymentSystem\Commands\CapturePaymentCommandInterface;
use PaymentSystem\Commands\CreateRefundCommandInterface;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Enum\RefundStatusEnum;
use PaymentSystem\Events\RefundCanceled;
use PaymentSystem\Events\RefundCreated;
use PaymentSystem\Events\RefundDeclined;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\Exceptions\RefundException;
use PaymentSystem\Gateway\Events\GatewayRefundCanceled;
use PaymentSystem\Gateway\Events\GatewayRefundCreated;
use PaymentSystem\Gateway\Resources\RefundInterface;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\RefundAggregateRoot;
use PaymentSystem\TenderInterface;
use PaymentSystem\Tests\Refunds;
use PaymentSystem\ValueObjects\CreditCard;
use PaymentSystem\ValueObjects\GenericId;

use function EventSauce\EventSourcing\PestTooling\given;
use function EventSauce\EventSourcing\PestTooling\when;

uses(Refunds::class);

describe('domain-first flow', function () {
    it('refund created successfully', function () {
        $paymentIntent = $this->createMock(PaymentIntentAggregateRoot::class);
        $paymentIntent->method('aggregateRootId')->willReturn(new GenericId(1));
        $paymentIntent->method('getStatus')->willReturn(PaymentIntentStatusEnum::SUCCEEDED);
        $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
        $paymentIntent->method('getMoney')->willReturn(new Money(100, new Currency('USD')));

        $command = $this->createStub(CreateRefundCommandInterface::class);
        $command->method('getPaymentIntent')->willReturn($paymentIntent);
        $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
        $command->method('getId')->willReturn(new GenericId(2));
        when(fn() => RefundAggregateRoot::create($command))
            ->then(new RefundCreated($command->getMoney(), $command->getPaymentIntent()->aggregateRootId()));

        expect($this->retrieveAggregateRoot(new GenericId(2)))
            ->toBeInstanceOf(RefundAggregateRoot::class)
            ->getPaymentIntentId()->toEqual(new GenericId(1))
            ->getMoney()->equals(new Money(100, new Currency('USD')))->toBeTrue();
    });
    it('partial refund created successfully', function () {
        $paymentIntent = $this->createMock(PaymentIntentAggregateRoot::class);
        $paymentIntent->method('aggregateRootId')->willReturn(new GenericId(1));
        $paymentIntent->method('getStatus')->willReturn(PaymentIntentStatusEnum::SUCCEEDED);
        $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
        $paymentIntent->method('getMoney')->willReturn(new Money(100, new Currency('USD')));

        $command = $this->createStub(CreateRefundCommandInterface::class);
        $command->method('getPaymentIntent')->willReturn($paymentIntent);
        $command->method('getMoney')->willReturn(new Money(50, new Currency('USD')));

        when(fn() => RefundAggregateRoot::create($command))
            ->then(new RefundCreated($command->getMoney(), $command->getPaymentIntent()->aggregateRootId()));
    });
    it('payment can be refunded multiple times', function () {
        $tender = $this->createStub(TenderInterface::class);
        $tender->method('isValid')->willReturn(true);
        $tender->method('getSource')->willReturn(new CreditCard(
            new CreditCard\Number('424242', '4242', 'visa'),
            CreditCard\Expiration::fromMonthAndYear(12, 34),
            new CreditCard\Holder('Andrea Palladio'),
            new CreditCard\Cvc(),
        ));

        $authorize = $this->createStub(AuthorizePaymentCommandInterface::class);
        $authorize->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
        $authorize->method('getId')->willReturn(new GenericId('test payment intent'));
        $authorize->method('getTender')->willReturn($tender);

        $this->repository->persist(
            $paymentIntent = PaymentIntentAggregateRoot::authorize($authorize)
                ->capture($this->createStub(CapturePaymentCommandInterface::class)),
        );
        $command = $this->createStub(CreateRefundCommandInterface::class);
        $command->method('getPaymentIntent')->willReturn($paymentIntent);
        $command->method('getMoney')->willReturn(new Money(50, new Currency('USD')));

        given(
            new RefundCreated($command->getMoney(), $command->getPaymentIntent()->aggregateRootId())
        )
            ->when(fn() => RefundAggregateRoot::create($command))
            ->then(new RefundCreated($command->getMoney(), $command->getPaymentIntent()->aggregateRootId()));
    });
    it('cannot refund zero', function () {
        $command = $this->createStub(CreateRefundCommandInterface::class);
        $command->method('getPaymentIntent')->willReturn($this->createStub(PaymentIntentAggregateRoot::class));
        $command->method('getMoney')->willReturn(new Money(0, new Currency('USD')));

        when(fn() => RefundAggregateRoot::create($command))
            ->expectToFail(InvalidAmountException::notZero());
    });
    it('cannot refund negative', function () {
        $command = $this->createStub(CreateRefundCommandInterface::class);
        $command->method('getPaymentIntent')->willReturn($this->createStub(PaymentIntentAggregateRoot::class));
        $command->method('getMoney')->willReturn(new Money(-100, new Currency('USD')));

        when(fn() => RefundAggregateRoot::create($command))
            ->expectToFail(InvalidAmountException::notNegative());
    });
    todo('cannot refund more than captured');
    it('created refund succeeded', function () {
        $gateway = $this->createStub(RefundInterface::class);
        $gateway->method('getMoney')->willReturn(new Money(100, new Currency('USD')));

        given(
            new RefundCreated(new Money(100, new Currency('USD')), $this->createStub(AggregateRootId::class)),
        )
            ->when(function(RefundAggregateRoot $refund) use($gateway) {
                $refund->getGatewayRefund()->create(fn() => $gateway);
                return $refund;
            })
            ->then(new GatewayRefundCreated($gateway));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(RefundAggregateRoot::class)
            ->is(RefundStatusEnum::SUCCEEDED);
    });
    it('created refund declined', function () {
        given(
            new RefundCreated(new Money(100, new Currency('USD')), $this->createStub(AggregateRootId::class)),
        )
            ->when(fn(RefundAggregateRoot $refund) => $refund->decline('test reason'))
            ->then(new RefundDeclined('test reason'));

        expect($this->retrieveAggregateRoot($this->aggregateRootId()))
            ->toBeInstanceOf(RefundAggregateRoot::class)
            ->is(RefundStatusEnum::DECLINED)->toBeTrue()
            ->getDeclineReason()->toBe('test reason');
    });
    it('created refund canceled', function () {
        given(
            new RefundCreated(new Money(100, new Currency('USD')), $this->createStub(AggregateRootId::class)),
        )
            ->when(fn(RefundAggregateRoot $refund) => $refund->cancel())
            ->then(new RefundCanceled());
    });
    it('cannot decline succeeded refund', function () {
        $gateway = $this->createStub(RefundInterface::class);
        $gateway->method('getMoney')->willReturn(new Money(0, new Currency('USD')));
        $gateway->method('getId')->willReturn($this->createStub(AggregateRootId::class));

        given(
            new RefundCreated(new Money(0, new Currency('USD')), $this->createStub(AggregateRootId::class)),
            new GatewayRefundCreated($gateway),
        )
            ->when(fn(RefundAggregateRoot $refund) => $refund->decline('test reason'))
            ->expectToFail(RefundException::cannotDecline(RefundStatusEnum::SUCCEEDED));
    });
    it('cannot cancel succeeded refund', function () {
        $gateway = $this->createStub(RefundInterface::class);
        $gateway->method('getMoney')->willReturn(new Money(0, new Currency('USD')));
        $gateway->method('getId')->willReturn($this->createStub(AggregateRootId::class));

        given(
            new RefundCreated(new Money(0, new Currency('USD')), $this->createStub(AggregateRootId::class)),
            new GatewayRefundCreated($gateway),
        )
            ->when(fn(RefundAggregateRoot $refund) => $refund->cancel())
            ->expectToFail(RefundException::cannotCancel(RefundStatusEnum::SUCCEEDED));
    });
    it('cannot cancel canceled refund', function () {
        given(
            new RefundCreated(new Money(0, new Currency('USD')), $this->createStub(AggregateRootId::class)),
            new RefundCanceled(),
        )
            ->when(fn(RefundAggregateRoot $refund) => $refund->cancel())
            ->expectToFail(RefundException::cannotCancel(RefundStatusEnum::CANCELED));
    });
    it('cannot decline declined refund', function () {
        given(
            new RefundCreated(new Money(0, new Currency('USD')), $this->createStub(AggregateRootId::class)),
            new RefundDeclined('test decline'),
        )
            ->when(fn(RefundAggregateRoot $refund) => $refund->decline('test reason 2'))
            ->expectToFail(RefundException::cannotDecline(RefundStatusEnum::DECLINED));
    });
    it('cannot cancel declined refund', function () {
        given(
            new RefundCreated(new Money(0, new Currency('USD')), $this->createStub(AggregateRootId::class)),
            new RefundDeclined('test decline'),
        )
            ->when(fn(RefundAggregateRoot $refund) => $refund->cancel())
            ->expectToFail(RefundException::cannotCancel(RefundStatusEnum::DECLINED));
    });
    it('cannot decline canceled refund', function () {
        given(
            new RefundCreated(new Money(0, new Currency('USD')), $this->createStub(AggregateRootId::class)),
            new RefundCanceled(),
        )
            ->when(fn(RefundAggregateRoot $refund) => $refund->decline('test reason'))
            ->expectToFail(RefundException::cannotDecline(RefundStatusEnum::CANCELED));
    });
});