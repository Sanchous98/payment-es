<?php

namespace PaymentSystem\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\BillingAddressAggregationRoot;

interface BillingAddressRepositoryInterface
{
    public function retrieve(AggregateRootId $id): BillingAddressAggregationRoot;

    public function persist(BillingAddressAggregationRoot $billingAddress): void;
}