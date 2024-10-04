<?php

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\ValueObjects\CreditCard;

interface CreateTokenCommandInterface
{
    public function getId(): AggregateRootId;

    public function getCard(): CreditCard;
}