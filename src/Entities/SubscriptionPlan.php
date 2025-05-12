<?php

namespace PaymentSystem\Entities;

use DateInterval;
use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\ValueObjects\MerchantDescriptor;

class SubscriptionPlan
{
    public function __construct(
        public readonly AggregateRootId $id,
        private(set) string $name,
        private(set) string $description,
        private(set) Money $money,
        private(set) DateInterval $interval,
        private(set) MerchantDescriptor $merchantDescriptor = new MerchantDescriptor(),
    ) {
    }

    public function update(
        ?string $name = null,
        ?string $description = null,
        ?Money $money = null,
        ?DateInterval $interval = null,
        ?MerchantDescriptor $merchantDescriptor = null
    ): void {
        $this->name = $name ?? $this->name;
        $this->description = $description ?? $this->description;
        $this->money = $money ?? $this->money;
        $this->interval = $interval ?? $this->interval;
        $this->merchantDescriptor = $merchantDescriptor ?? $this->merchantDescriptor;
    }
}