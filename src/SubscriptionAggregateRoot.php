<?php

declare(strict_types=1);

namespace PaymentSystem;

use DateTimeImmutable;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Commands\CreateSubscriptionCommandInterface;
use PaymentSystem\Entities\SubscriptionPlan;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Enum\SubscriptionStatusEnum;
use PaymentSystem\Events\SubscriptionCanceled;
use PaymentSystem\Events\SubscriptionCreated;
use PaymentSystem\Events\SubscriptionPaid;
use PaymentSystem\Events\SubscriptionPaymentMethodUpdated;
use PaymentSystem\Exceptions\PaymentMethodException;
use PaymentSystem\Exceptions\SubscriptionException;
use PaymentSystem\ValueObjects\RecurringActionTracker;
use Psr\Clock\ClockInterface;

use function in_array;
use function array_map;

class SubscriptionAggregateRoot implements AggregateRoot
{
    use AggregateRootBehaviour;

    public const string GRACE_PERIOD = 'P1D';

    private(set) SubscriptionPlan $plan;

    private(set) AggregateRootId $paymentMethodId;

    /**
     * @var AggregateRootId[]
     */
    private(set) array $payments = [];

    private(set) bool $canceled = false;

    private RecurringActionTracker $tracker;

    public static function create(CreateSubscriptionCommandInterface $command): static
    {
        $command->paymentMethod->isValid() || throw PaymentMethodException::suspended();

        $self = new self($command->id);
        $self->recordThat(new SubscriptionCreated($command->plan, $command->paymentMethod->aggregateRootId()));

        return $self;
    }

    public function pay(PaymentIntentAggregateRoot $paymentIntent, ClockInterface $clock): static
    {
        $paymentIntent->subscriptionId !== null || throw SubscriptionException::paymentIntentNotAttached();
        $paymentIntent->subscriptionId->toString() === $this->aggregateRootId()->toString() || throw SubscriptionException::paymentIntentNotAttachedToThis();
        $paymentIntent->tenderId->toString() === $this->paymentMethodId->toString() || throw SubscriptionException::paymentMethodMismatch();
        $paymentIntent->is(PaymentIntentStatusEnum::SUCCEEDED) || throw SubscriptionException::paymentIntentNotSucceeded();
        $paymentIntent->money->equals($this->plan->money) || throw SubscriptionException::moneyMismatch();
        in_array($paymentIntent->aggregateRootId()->toString(), array_map(fn(AggregateRootId $id) => $id->toString(), $this->payments), true) && throw SubscriptionException::paymentIntentAlreadyUsed();

        $this->recordThat(new SubscriptionPaid($paymentIntent->aggregateRootId(), $clock->now()));

        return $this;
    }

    public function updatePaymentMethod(PaymentMethodAggregateRoot $paymentMethod): static
    {
        $paymentMethod->isValid() || throw PaymentMethodException::suspended();
        $this->recordThat(new SubscriptionPaymentMethodUpdated($paymentMethod->aggregateRootId()));

        return $this;
    }

    public function cancel(): static
    {
        $this->is(SubscriptionStatusEnum::CANCELLED) && throw SubscriptionException::cannotCancel(SubscriptionStatusEnum::CANCELLED);
        $this->recordThat(new SubscriptionCanceled());

        return $this;
    }

    public function getStatus(): SubscriptionStatusEnum
    {
        if ($this->canceled) {
            return SubscriptionStatusEnum::CANCELED;
        }

        $now = new DateTimeImmutable();
        $endDate = $this->tracker->getEndDate();

        if ($endDate >= $now) {
            return SubscriptionStatusEnum::ACTIVE;
        }

        $gracePeriodEnd = $endDate->add(new \DateInterval(self::GRACE_PERIOD));

        return $now <= $gracePeriodEnd ? SubscriptionStatusEnum::PENDING : SubscriptionStatusEnum::SUSPENDED;
    }

    public function is(SubscriptionStatusEnum $status): bool
    {
        return $this->getStatus() === $status;
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->tracker->getEndDate();
    }

    protected function apply(object $event): void
    {
        switch ($event::class) {
            case SubscriptionCreated::class:
                $this->applySubscriptionCreated($event);
                return;
            case SubscriptionPaid::class:
                $this->applySubscriptionPaid($event);
                return;
            case SubscriptionPaymentMethodUpdated::class:
                $this->applySubscriptionPaymentMethodUpdated($event);
                return;
            case SubscriptionCanceled::class:
                $this->applySubscriptionCanceled();
                return;
        }
    }

    protected function applySubscriptionCreated(SubscriptionCreated $event): void
    {
        $this->plan = $event->plan;
        $this->paymentMethodId = $event->paymentMethodId;
        $this->tracker = new RecurringActionTracker($this->plan->interval, new DateTimeImmutable()->setTime(0, 0));
    }

    protected function applySubscriptionPaid(SubscriptionPaid $event): void
    {
        $this->tracker ??= new RecurringActionTracker($this->plan->interval, $event->when->setTime(0, 0));
        $this->tracker->advance($event->when);
        $this->payments[] = $event->paymentIntentId;
    }

    protected function applySubscriptionPaymentMethodUpdated(SubscriptionPaymentMethodUpdated $event): void
    {
        $this->paymentMethodId = $event->paymentMethodId;
    }

    protected function applySubscriptionCanceled(): void
    {
        $this->canceled = true;
    }
}