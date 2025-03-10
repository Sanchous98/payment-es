<?php

declare(strict_types=1);

namespace PaymentSystem\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\TokenAggregateRoot;

interface TokenRepositoryInterface
{
    public function retrieve(AggregateRootId $id): TokenAggregateRoot;

    public function persist(TokenAggregateRoot $token): void;
}