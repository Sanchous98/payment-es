<?php

declare(strict_types=1);

namespace PaymentSystem\Commands;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Entities\BillingAddress;
use PaymentSystem\ValueObjects\CreditCard;

interface CreateTokenCommandInterface
{
    public function getId(): AggregateRootId;

    public function getCard(): CreditCard;

    public function getBillingAddress(): ?BillingAddress;
}