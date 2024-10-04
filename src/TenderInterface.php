<?php

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use PaymentSystem\Contracts\SourceInterface;

interface TenderInterface extends AggregateRoot
{
    public function isValid(): bool;

    public function getSource(): SourceInterface;
}