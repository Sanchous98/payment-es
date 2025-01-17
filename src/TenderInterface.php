<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Gateway\Resources\PaymentMethodInterface;
use PaymentSystem\Gateway\Resources\TokenInterface;

interface TenderInterface extends AggregateRoot
{
    public function isValid(): bool;

    public function getSource(): SourceInterface;

    public function use(?callable $callback = null): TenderInterface;

    /**
     * @return PaymentMethodInterface[]|TokenInterface[]
     */
    public function getGatewayTenders(): array;
}