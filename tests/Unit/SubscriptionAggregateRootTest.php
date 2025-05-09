<?php

use EventSauce\Clock\TestClock;
use Money\Currency;
use Money\Money;
use PaymentSystem\Commands\CreateSubscriptionCommandInterface;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Enum\SubscriptionStatusEnum;
use PaymentSystem\Events\SubscriptionCanceled;
use PaymentSystem\Events\SubscriptionCreated;
use PaymentSystem\Events\SubscriptionPaid;
use PaymentSystem\Exceptions\SubscriptionException;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\SubscriptionAggregateRoot;
use PaymentSystem\ValueObjects\GenericId;
use PaymentSystem\Entities\SubscriptionPlan;
use PaymentSystem\Tests\Subscriptions;

use function EventSauce\EventSourcing\PestTooling\given;
use function EventSauce\EventSourcing\PestTooling\when;

uses(Subscriptions::class);

it('is created successfully', function () {
    $paymentMethod = $this->createStub(PaymentMethodAggregateRoot::class);
    $paymentMethod->method('aggregateRootId')->willReturn(new GenericId('1'));
    $paymentMethod->method('isValid')->willReturn(true);

    $command = $this->createStub(CreateSubscriptionCommandInterface::class);
    $command->method('getPaymentMethod')->willReturn($paymentMethod);
    $command->method('getPlan')->willReturn(new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    ));

    when(fn() => SubscriptionAggregateRoot::create($command))
        ->then(new SubscriptionCreated($command->getPlan(), $command->getPaymentMethod()->aggregateRootId()));
});

it('is paid successfully when pending', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
    $paymentIntent->method('getMoney')->willReturn($plan->money);
    $clock = new TestClock();

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->then(new SubscriptionPaid($paymentIntent->aggregateRootId(), $clock->now()));
});

it('transitions from pending to active after payment', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
    $paymentIntent->method('getMoney')->willReturn($plan->money);
    
    $clock = new TestClock();

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()),)
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->then(
            new SubscriptionPaid($paymentIntent->aggregateRootId(), $clock->now())
        );

    expect($this->retrieveAggregateRoot($this->aggregateRootId()))
        ->toBeInstanceOf(SubscriptionAggregateRoot::class)
        ->is(SubscriptionStatusEnum::ACTIVE)->toBeTrue();
});
it('transitions from active to pending after period ends', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
    $paymentIntent->method('getMoney')->willReturn($plan->money);
    
    $clock = new TestClock();
    $clock->moveForward(DateInterval::createFromDateString('-1 day'));

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->then(new SubscriptionPaid($paymentIntent->aggregateRootId(), $clock->now()));

    $subscription = $this->retrieveAggregateRoot($this->aggregateRootId());
    expect($subscription)
        ->toBeInstanceOf(SubscriptionAggregateRoot::class)
        ->is(SubscriptionStatusEnum::PENDING)->toBeTrue();
});
it('transitions from pending to suspended after grace period', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
    $paymentIntent->method('getMoney')->willReturn($plan->money);
    
    $clock = new TestClock();
    $clock->moveForward(DateInterval::createFromDateString('-2 day'));
    
    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->then(new SubscriptionPaid($paymentIntent->aggregateRootId(), $clock->now()));
    
    $subscription = $this->retrieveAggregateRoot($this->aggregateRootId());
    expect($subscription)
        ->toBeInstanceOf(SubscriptionAggregateRoot::class)
        ->is(SubscriptionStatusEnum::SUSPENDED)->toBeTrue();
});
it('transitions from suspended to active after payment', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
    $paymentIntent->method('getMoney')->willReturn($plan->money);
    
    $clock = new TestClock();

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()),
        new SubscriptionPaid(new GenericId(2), $clock->now()->sub(DateInterval::createFromDateString('2 day'))))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->then(new SubscriptionPaid($paymentIntent->aggregateRootId(), $clock->now()));

    $subscription = $this->retrieveAggregateRoot($this->aggregateRootId());
    expect($subscription)
        ->toBeInstanceOf(SubscriptionAggregateRoot::class)
        ->getStatus()->toBe(SubscriptionStatusEnum::ACTIVE);
});

it('fails to pay with unattached payment intent', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );

    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn(null);

    $clock = new TestClock();

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->expectToFail(SubscriptionException::paymentIntentNotAttached());
});
it('fails to pay with attached payment intent to different subscription', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn(new GenericId('different-subscription-id'));
    
    $clock = new TestClock();

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->expectToFail(SubscriptionException::paymentIntentNotAttachedToThis());
});
it('fails to pay with unsuccessful payment intent', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(false);
    
    $clock = new TestClock();

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->expectToFail(SubscriptionException::paymentIntentNotSucceeded());
});
it('fails to pay with wrong amount', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
    $paymentIntent->method('getMoney')->willReturn(new Money(200, new Currency('USD')));
    
    $clock = new TestClock();

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->expectToFail(SubscriptionException::moneyMismatch());
});
it('fails to pay with already used payment intent', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
    $paymentIntent->method('getMoney')->willReturn($plan->money);
    
    $clock = new TestClock();

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()),
        new SubscriptionPaid($paymentIntent->aggregateRootId(), $clock->now()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->expectToFail(SubscriptionException::paymentIntentAlreadyUsed());
});

it('can cancel active subscription', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
    $paymentIntent->method('getMoney')->willReturn($plan->money);
    
    $clock = new TestClock();

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()),
        new SubscriptionPaid($paymentIntent->aggregateRootId(), $clock->now()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->cancel())
        ->then(new SubscriptionCanceled());

    $subscription = $this->retrieveAggregateRoot($this->aggregateRootId());
    expect($subscription)
        ->toBeInstanceOf(SubscriptionAggregateRoot::class)
        ->is(SubscriptionStatusEnum::CANCELLED)->toBeTrue();
});
it('can cancel pending subscription', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );

    given(new SubscriptionCreated($plan, $this->aggregateRootId()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->cancel())
        ->then(new SubscriptionCanceled());

    $subscription = $this->retrieveAggregateRoot($this->aggregateRootId());
    expect($subscription)
        ->toBeInstanceOf(SubscriptionAggregateRoot::class)
        ->is(SubscriptionStatusEnum::CANCELLED)->toBeTrue();
});
it('can cancel suspended subscription', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
    $paymentIntent->method('getMoney')->willReturn($plan->money);
    
    $clock = new TestClock();
    $clock->moveForward(DateInterval::createFromDateString('-2 day'));

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()),
        new SubscriptionPaid($paymentIntent->aggregateRootId(), $clock->now()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->cancel())
        ->then(new SubscriptionCanceled($clock->now()));

    $subscription = $this->retrieveAggregateRoot($this->aggregateRootId());
    expect($subscription)
        ->toBeInstanceOf(SubscriptionAggregateRoot::class)
        ->is(SubscriptionStatusEnum::CANCELLED)->toBeTrue();
});
it('cannot cancel already cancelled subscription', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );

    given(new SubscriptionCreated($plan, $this->aggregateRootId()),
        new SubscriptionCanceled())
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->cancel())
        ->expectToFail(SubscriptionException::cannotCancel(SubscriptionStatusEnum::CANCELLED));;
});

it('starts grace period on period end', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
    $paymentIntent->method('getMoney')->willReturn($plan->money);
    
    $clock = new TestClock();
    $clock->moveForward(DateInterval::createFromDateString('-1 day'));

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->then(new SubscriptionPaid($paymentIntent->aggregateRootId(), $clock->now()));

    $subscription = $this->retrieveAggregateRoot($this->aggregateRootId());
    expect($subscription)
        ->toBeInstanceOf(SubscriptionAggregateRoot::class)
        ->getStatus()->toBe(SubscriptionStatusEnum::PENDING);
});
it('suspends after grace period ends', function () {
    $plan = new SubscriptionPlan(
        new GenericId('sp-id'),
        'test',
        'test description',
        new Money(100, new Currency('USD')),
        new DateInterval('P1D')
    );
    
    $paymentIntent = $this->createStub(PaymentIntentAggregateRoot::class);
    $paymentIntent->method('getTenderId')->willReturn(new GenericId('2'));
    $paymentIntent->method('getSubscriptionId')->willReturn($this->aggregateRootId());
    $paymentIntent->method('is')->with(PaymentIntentStatusEnum::SUCCEEDED)->willReturn(true);
    $paymentIntent->method('getMoney')->willReturn($plan->money);
    
    $clock = new TestClock();
    $clock->moveForward(DateInterval::createFromDateString('-2 day'));

    given(new SubscriptionCreated($plan, $paymentIntent->getTenderId()))
        ->when(fn(SubscriptionAggregateRoot $subscription) => $subscription->pay($paymentIntent, $clock))
        ->then(new SubscriptionPaid($paymentIntent->aggregateRootId(), $clock->now()));

    $subscription = $this->retrieveAggregateRoot($this->aggregateRootId());
    expect($subscription)
        ->toBeInstanceOf(SubscriptionAggregateRoot::class)
        ->getStatus()->toBe(SubscriptionStatusEnum::SUSPENDED);
});