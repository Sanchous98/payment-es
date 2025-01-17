<?php

declare(strict_types=1);

namespace PaymentSystem\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\PaymentIntentAggregateRoot;

interface PaymentIntentRepositoryInterface
{
    public function retrieve(AggregateRootId $id): PaymentIntentAggregateRoot;

    public function persist(PaymentIntentAggregateRoot $paymentIntent): void;
}
