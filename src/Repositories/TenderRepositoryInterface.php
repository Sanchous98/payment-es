<?php

namespace PaymentSystem\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\TenderInterface;

interface TenderRepositoryInterface
{
    public function retrieve(AggregateRootId $id): TenderInterface;

    public function persist(TenderInterface $tender): void;
}