<?php

declare(strict_types=1);

namespace PaymentSystem\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\SubscriptionAggregateRoot;

interface SubscriptionRepositoryInterface
{

    public function retrieve(AggregateRootId $id): SubscriptionAggregateRoot;

    public function persist(SubscriptionAggregateRoot $subscription): void;
}