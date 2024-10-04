<?php

use Money\Currency;
use Money\Money;
use PaymentSystem\Commands\CreateRefundCommandInterface;
use PaymentSystem\Enum\RefundStatusEnum;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\Exceptions\RefundException;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\RefundAggregateRoot;
use PaymentSystem\Tests\IntId;

test('refund created successfully', function (PaymentIntentAggregateRoot $paymentIntent) {
    $command = $this->createStub(CreateRefundCommandInterface::class);
    $command->method('getId')->willReturn(new IntId(1));
    $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
    $command->method('getPaymentIntent')->willReturn($paymentIntent);

    $refund = RefundAggregateRoot::create($command);

    expect($refund)
        ->is(RefundStatusEnum::CREATED)->toBeTrue()
        ->getMoney()->toEqual(new Money(100, new Currency('USD')));
})->with('captured payment');

test('partial refund created successfully', function (PaymentIntentAggregateRoot $paymentIntent) {
    $command = $this->createStub(CreateRefundCommandInterface::class);
    $command->method('getId')->willReturn(new IntId(1));
    $command->method('getMoney')->willReturn(new Money(50, new Currency('USD')));
    $command->method('getPaymentIntent')->willReturn($paymentIntent);

    $refund = RefundAggregateRoot::create($command);

    expect($refund)
        ->is(RefundStatusEnum::CREATED)->toBeTrue()
        ->getMoney()->toEqual(new Money(50, new Currency('USD')));
})->with('captured payment');

test('payment can be refunded multiple times', function (PaymentIntentAggregateRoot $paymentIntent) {
    $command = $this->createStub(CreateRefundCommandInterface::class);
    $command->method('getId')->willReturn(new IntId(1));
    $command->method('getMoney')->willReturn(new Money(50, new Currency('USD')));
    $command->method('getPaymentIntent')->willReturn($paymentIntent);

    $firstRefund = RefundAggregateRoot::create($command);

    $secondRefund = RefundAggregateRoot::create($command);

    expect($firstRefund)
        ->is(RefundStatusEnum::CREATED)->toBeTrue()
        ->getMoney()->toEqual(new Money(50, new Currency('USD')))
        ->and($secondRefund)
        ->is(RefundStatusEnum::CREATED)->toBeTrue()
        ->getMoney()->toEqual(new Money(50, new Currency('USD')));
})->with('captured payment');

test('cannot refund zero', function (PaymentIntentAggregateRoot $paymentIntent) {
    $command = $this->createStub(CreateRefundCommandInterface::class);
    $command->method('getMoney')->willReturn(new Money(0, new Currency('USD')));
    $command->method('getPaymentIntent')->willReturn($paymentIntent);

    $refund = RefundAggregateRoot::reconstituteFromEvents(new IntId(1), generator());
    $refund->create($command);
})->with('captured payment')->throws(InvalidAmountException::class);

test('cannot refund negative', function (PaymentIntentAggregateRoot $paymentIntent) {
    $command = $this->createStub(CreateRefundCommandInterface::class);
    $command->method('getMoney')->willReturn(new Money(-100, new Currency('USD')));
    $command->method('getPaymentIntent')->willReturn($paymentIntent);

    $refund = RefundAggregateRoot::reconstituteFromEvents(new IntId(1), generator());
    $refund->create($command);
})->with('captured payment')->throws(InvalidAmountException::class);

test('cannot refund more than captured', function (PaymentIntentAggregateRoot $paymentIntent) {
    $command = $this->createStub(CreateRefundCommandInterface::class);
    $command->method('getMoney')->willReturn(new Money(200, new Currency('USD')));
    $command->method('getPaymentIntent')->willReturn($paymentIntent);

    $refund = RefundAggregateRoot::reconstituteFromEvents(new IntId(1), generator());
    $refund->create($command);
})->with('captured payment')->throws(InvalidAmountException::class);

test('created refund succeeded', function (RefundAggregateRoot $refund) {
    $refund->success();

    expect($refund)->is(RefundStatusEnum::SUCCEEDED)->toBeTrue();
})->with('created refund');

test('created refund declined', function (RefundAggregateRoot $refund) {
    $refund->decline('test reason');

    expect($refund)
        ->is(RefundStatusEnum::DECLINED)->toBeTrue()
        ->getDeclineReason()->toEqual('test reason');
})->with('created refund');

test('created refund canceled', function (RefundAggregateRoot $refund) {
    $refund->cancel();

    expect($refund)->is(RefundStatusEnum::CANCELED)->toBeTrue();
})->with('created refund');

test('cannot decline succeeded refund', function (RefundAggregateRoot $refund) {
    $refund->decline('');
})->with('succeeded refund')->throws(RefundException::class);

test('cannot cancel succeeded refund', function (RefundAggregateRoot $refund) {
    $refund->cancel();

    expect($refund)->is(RefundStatusEnum::CANCELED)->toBeTrue();
})->with('canceled refund')->throws(RefundException::class);

test('cannot succeed declined refund', function (RefundAggregateRoot $refund) {
    $refund->success();
})->with('declined refund')->throws(RefundException::class);

test('cannot succeed canceled refund', function (RefundAggregateRoot $refund) {
    $refund->success();
})->with('canceled refund')->throws(RefundException::class);

test('cannot succeed succeeded refund', function (RefundAggregateRoot $refund) {
    $refund->success();
})->with('succeeded refund')->throws(RefundException::class);

test('cannot cancel canceled refund', function (RefundAggregateRoot $refund) {
    $refund->cancel();
})->with('canceled refund')->throws(RefundException::class);

test('cannot decline declined refund', function (RefundAggregateRoot $refund) {
    $refund->decline('');
})->with('declined refund')->throws(RefundException::class);

test('cannot cancel declined refund', function (RefundAggregateRoot $refund) {
    $refund->cancel();
})->with('declined refund')->throws(RefundException::class);

test('cannot decline canceled refund', function (RefundAggregateRoot $refund) {
    $refund->decline('');
})->with('canceled refund')->throws(RefundException::class);