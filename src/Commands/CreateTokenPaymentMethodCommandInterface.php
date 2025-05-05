<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\TokenAggregateRoot;

interface CreateTokenPaymentMethodCommandInterface
{
    public function getId(): AggregateRootId;

    public function getToken(): TokenAggregateRoot;
}