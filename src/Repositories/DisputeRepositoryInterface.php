<?php

declare(strict_types=1);

namespace PaymentSystem\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\DisputeAggregateRoot;

interface DisputeRepositoryInterface
{
    public function retrieve(AggregateRootId $id): DisputeAggregateRoot;

    public function persist(DisputeAggregateRoot $dispute): void;
}