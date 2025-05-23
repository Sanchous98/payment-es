<?php

declare(strict_types=1);

namespace PaymentSystem\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\PaymentMethodAggregateRoot;

interface PaymentMethodRepositoryInterface
{
    public function retrieve(AggregateRootId $id): PaymentMethodAggregateRoot;

    public function persist(PaymentMethodAggregateRoot $paymentMethod): void;
}
