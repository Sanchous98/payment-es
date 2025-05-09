<?php

namespace PaymentSystem\Entities;

use DateInterval;
use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\ValueObjects\MerchantDescriptor;

class SubscriptionPlan
{
    public function __construct(
        private readonly AggregateRootId $id,
        public string $name {
            get => $this->name;
        },
        public string $description {
            get => $this->description;
        },
        public Money $money {
            get => $this->money;
        },
        public DateInterval $interval {
            get => $this->interval;
        },
        public MerchantDescriptor $merchantDescriptor = new MerchantDescriptor() {
            get => $this->merchantDescriptor;
        },
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

    public function getId(): AggregateRootId
    {
        return $this->id;
    }
}