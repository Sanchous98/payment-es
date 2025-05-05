<?php

declare(strict_types=1);

namespace PaymentSystem\Events;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\SubscriptionAggregateRoot;
use PaymentSystem\ValueObjects\MerchantDescriptor;
use PaymentSystem\ValueObjects\ThreeDSResult;

readonly final class PaymentIntentAuthorized
{
    public function __construct(
        public Money $money,
        public ?AggregateRootId $tenderId = null,
        public MerchantDescriptor $merchantDescriptor = new MerchantDescriptor(),
        public string $description = '',
        public ?ThreeDSResult $threeDSResult = null,
        public ?AggregateRootId $subscriptionId = null,
    ) {
    }
}