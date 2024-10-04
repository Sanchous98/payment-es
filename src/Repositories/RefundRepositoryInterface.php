<?php

namespace PaymentSystem\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\RefundAggregateRoot;

interface RefundRepositoryInterface
{
    public function retrieve(AggregateRootId $id): RefundAggregateRoot;

    public function persist(RefundAggregateRoot $refund): void;
}
