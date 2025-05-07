<?php

namespace PaymentSystem\Gateway\Resources;

use EventSauce\EventSourcing\AggregateRootId;
use Money\Money;
use PaymentSystem\ValueObjects\MerchantDescriptor;
use PaymentSystem\ValueObjects\ThreeDSResult;

interface PaymentIntentInterface extends ResourceInterface
{
    public function getMoney(): Money;

    public function getMerchantDescriptor(): MerchantDescriptor;

    public function getDescription(): string;

    public function getPaymentMethodId(): ?AggregateRootId;

    public function getThreeDS(): ?ThreeDSResult;
}