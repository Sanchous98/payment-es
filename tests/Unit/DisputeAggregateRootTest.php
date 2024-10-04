<?php

use Money\Currency;
use Money\Money;
use PaymentSystem\Commands\CreateDisputeCommandInterface;
use PaymentSystem\DisputeAggregateRoot;
use PaymentSystem\Enum\DisputeStatusEnum;
use PaymentSystem\Events\DisputeCreated;
use PaymentSystem\Exceptions\InvalidAmountException;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\Tests\IntId;

test('dispute created', function (PaymentIntentAggregateRoot $paymentIntent) {
    $command = $this->createStub(CreateDisputeCommandInterface::class);
    $command->method('getId')->willReturn(new IntId(2));
    $command->method('getPaymentIntent')->willReturn($paymentIntent);
    $command->method('getReason')->willReturn('');
    $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
    $command->method('getFee')->willReturn(new Money(5, new Currency('USD')));

    $dispute = DisputeAggregateRoot::create($command);

    expect($dispute)
        ->getReason()->toBe('')
        ->getFee()->toEqual(new Money(5, new Currency('USD')))
        ->getMoney()->toEqual(new Money(100, new Currency('USD')))
        ->is(DisputeStatusEnum::CREATED)->toBeTrue()
        ->getPaymentIntentId()->toBe($paymentIntent->aggregateRootId());
})->with('captured payment');

test('dispute won', function (PaymentIntentAggregateRoot $paymentIntent) {
    $dispute = DisputeAggregateRoot::reconstituteFromEvents(new IntId(2), generator([
        new DisputeCreated($paymentIntent->aggregateRootId(), new Money(100, new Currency('USD')), new Money(5, new Currency('USD')), '')
    ]));
    $dispute->win();

    expect($dispute)
        ->getReason()->toBe('')
        ->getFee()->toEqual(new Money(5, new Currency('USD')))
        ->getMoney()->toEqual(new Money(100, new Currency('USD')))
        ->is(DisputeStatusEnum::WON)->toBeTrue()
        ->getPaymentIntentId()->toBe($paymentIntent->aggregateRootId());
})->with('captured payment');

test('dispute lost', function (PaymentIntentAggregateRoot $paymentIntent) {
    $dispute = DisputeAggregateRoot::reconstituteFromEvents(new IntId(2), generator([
        new DisputeCreated($paymentIntent->aggregateRootId(), new Money(100, new Currency('USD')), new Money(5, new Currency('USD')), '')
    ]));
    $dispute->loose();

    expect($dispute)
        ->getReason()->toBe('')
        ->getFee()->toEqual(new Money(5, new Currency('USD')))
        ->getMoney()->toEqual(new Money(100, new Currency('USD')))
        ->is(DisputeStatusEnum::LOST)->toBeTrue()
        ->getPaymentIntentId()->toBe($paymentIntent->aggregateRootId());
})->with('captured payment');

test('dispute cannot be more than payment', function (PaymentIntentAggregateRoot $paymentIntent) {
    $command = $this->createStub(CreateDisputeCommandInterface::class);
    $command->method('getPaymentIntent')->willReturn($paymentIntent);
    $command->method('getReason')->willReturn('');
    $command->method('getMoney')->willReturn(new Money(200, new Currency('USD')));
    $command->method('getFee')->willReturn(new Money(5, new Currency('USD')));

    $dispute = DisputeAggregateRoot::reconstituteFromEvents(new IntId(2), generator());
    $dispute->create($command);
})->with('captured payment')->throws(InvalidAmountException::class);