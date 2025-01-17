<?php

namespace PaymentSystem\Gateway\Resources;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;

interface RefundInterface extends ResourceInterface
{
    public function getMoney(): Money;

    public function getPaymentIntentId(): AggregateRootId;
}