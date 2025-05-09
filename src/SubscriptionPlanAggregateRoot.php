<?php

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use PaymentSystem\Commands\CreateSubscriptionPlanCommandInterface;
use PaymentSystem\Commands\UpdateSubscriptionPlanCommandInterface;
use PaymentSystem\Entities\SubscriptionPlan;
use PaymentSystem\Events\SubscriptionPlanCreated;
use PaymentSystem\Events\SubscriptionPlanDeleted;
use PaymentSystem\Events\SubscriptionPlanUpdated;

class SubscriptionPlanAggregateRoot implements AggregateRoot
{
    use AggregateRootBehaviour;

    private SubscriptionPlan $plan;

    public function getPlan(): SubscriptionPlan
    {
        return $this->plan;
    }

    public static function create(CreateSubscriptionPlanCommandInterface $command): static
    {
        $self = new static($command->getId());
        $self->recordThat(
            new SubscriptionPlanCreated(
                $command->getName(),
                $command->getDescription(),
                $command->getMoney(),
                $command->getInterval(),
                $command->getMerchantDescriptor(),
            )
        );

        return $self;
    }

    public function update(UpdateSubscriptionPlanCommandInterface $command): static
    {
        $this->plan !== null || throw new \RuntimeException('Cannot update non-existing plan');

        $this->recordThat(
            new SubscriptionPlanUpdated(
                $command->getName(),
                $command->getDescription(),
                $command->getMoney(),
                $command->getInterval(),
                $command->getMerchantDescriptor(),
            )
        );

        return $this;
    }

    public function delete(): self
    {
        $this->plan !== null || throw new \RuntimeException('Cannot delete non-existing plan');
        $this->recordThat(new SubscriptionPlanDeleted());

        return $this;
    }

    protected function applySubscriptionPlanCreated(SubscriptionPlanCreated $event): void
    {
        $this->plan = new SubscriptionPlan(
            $this->aggregateRootId(),
            $event->name,
            $event->description,
            $event->money,
            $event->interval,
            $event->merchantDescriptor
        );
    }

    protected function applySubscriptionPlanUpdated(SubscriptionPlanUpdated $event): void
    {
        $this->plan->update(
            $event->name,
            $event->description,
            $event->money,
            $event->interval,
            $event->merchantDescriptor
        );
    }

    protected function applySubscriptionPlanDeleted(): void
    {
        unset($this->plan);
    }
}