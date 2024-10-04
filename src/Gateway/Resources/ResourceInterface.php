<?php

namespace PaymentSystem\Gateway\Resources;

use EventSauce\EventSourcing\AggregateRootId;

interface ResourceInterface
{
    public function getId(): AggregateRootId;

    public function getGatewayId(): AggregateRootId;

    public function getRawData(): array;

    public function isValid(): bool;
}