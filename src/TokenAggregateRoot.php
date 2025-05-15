<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootWithAggregates;
use PaymentSystem\Commands\CreateTokenCommandInterface;
use PaymentSystem\Contracts\TokenizableSourceInterface;
use PaymentSystem\Entities\BillingAddress;
use PaymentSystem\Enum\TokenStatusEnum;
use PaymentSystem\Exceptions\CardException;
use PaymentSystem\Exceptions\TokenException;

class TokenAggregateRoot implements AggregateRoot, TenderInterface
{
    use AggregateRootWithAggregates;

    private(set) ?BillingAddress $billingAddress = null;

    private(set) TokenizableSourceInterface $source;

    private(set) TokenStatusEnum $status;

    private(set) string $declineReason = '';

    public function is(TokenStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public function isValid(): bool
    {
        return $this->is(TokenStatusEnum::VALID);
    }

    public static function create(CreateTokenCommandInterface $command): static
    {
        $command->source->isValid() || throw CardException::expired();

        $self = new static($command->id);
        $self->recordThat(new Events\TokenCreated($command->source, $command->billingAddress));

        return $self;
    }

    public function use(?callable $callback = null): static
    {
        $this->isValid() || throw TokenException::suspended();

        isset($callback) && $callback($this);
        $this->recordThat(new Events\TokenUsed());

        return $this;
    }

    public function decline(string $reason): static
    {
        $this->isValid() || throw TokenException::suspended();

        $this->recordThat(new Events\TokenDeclined($reason));

        return $this;
    }

    // Event Listeners
    protected function applyTokenCreated(Events\TokenCreated $event): void
    {
        $this->source = $event->source;
        $this->billingAddress = $event->billingAddress;
        $this->status = TokenStatusEnum::PENDING;
    }

    protected function applyGatewayTokenAdded(): void
    {
        $this->status = TokenStatusEnum::VALID;
    }

    protected function applyTokenDeclined(Events\TokenDeclined $event): void
    {
        $this->status = TokenStatusEnum::DECLINED;
        $this->declineReason = $event->reason;
    }

    protected function applyTokenUsed(): void
    {
        $this->status = TokenStatusEnum::USED;
    }
}