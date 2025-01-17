<?php

declare(strict_types=1);

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\ValueObjects\BillingAddress;

readonly final class PaymentMethodCreated
{
    public function __construct(
        public BillingAddress $billingAddress,
        public SourceInterface $source,
        public ?AggregateRootId $tokenId = null,
    ) {
    }
}