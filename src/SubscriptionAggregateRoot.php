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

    private SubscriptionPlan $plan;

    private AggregateRootId $paymentMethodId;

    /**
     * @var AggregateRootId[]
     */
    private array $payments = [];

    private bool $canceled = false;

    private RecurringActionTracker $tracker;

    public static function create(CreateSubscriptionCommandInterface $command): static
    {
        $command->getPaymentMethod()->isValid() || throw PaymentMethodException::suspended();

        $self = new self($command->getId());
        $self->recordThat(new SubscriptionCreated($command->getPlan(), $command->getPaymentMethod()->aggregateRootId()));

        return $self;
    }

    public function pay(PaymentIntentAggregateRoot $paymentIntent, ClockInterface $clock): static
    {
        $paymentIntent->getSubscriptionId() !== null || throw SubscriptionException::paymentIntentNotAttached();
        $paymentIntent->getSubscriptionId()->toString() === $this->aggregateRootId()->toString() || throw SubscriptionException::paymentIntentNotAttachedToThis();
        $paymentIntent->getTenderId()->toString() === $this->paymentMethodId->toString() || throw SubscriptionException::paymentMethodMismatch();
        $paymentIntent->is(PaymentIntentStatusEnum::SUCCEEDED) || throw SubscriptionException::paymentIntentNotSucceeded();
        $paymentIntent->getMoney()->equals($this->plan->money) || throw SubscriptionException::moneyMismatch();
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