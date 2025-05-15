<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Entities\BillingAddress;

interface TenderInterface extends AggregateRoot
{
    public SourceInterface $source { get; }

    public ?BillingAddress $billingAddress { get; }

    public function isValid(): bool;

    public function use(?callable $callback = null): TenderInterface;
}