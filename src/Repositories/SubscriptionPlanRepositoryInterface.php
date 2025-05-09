<?php

namespace PaymentSystem\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\SubscriptionPlanAggregateRoot;

interface SubscriptionPlanRepositoryInterface
{
    public function retrieve(AggregateRootId $id): SubscriptionPlanAggregateRoot;

    public function persist(SubscriptionPlanAggregateRoot $subscriptionPlan): void;
}