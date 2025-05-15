<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use PaymentSystem\Commands\CreateTokenCommandInterface;
use PaymentSystem\Contracts\TokenizedSourceInterface;
use PaymentSystem\Entities\BillingAddress;
use PaymentSystem\Enum\TokenStatusEnum;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Events\TokenDeclined;
use PaymentSystem\Events\TokenUsed;
use PaymentSystem\Exceptions\CardException;
use PaymentSystem\Exceptions\TokenException;

class TokenAggregateRoot implements AggregateRoot, TenderInterface
{
    use AggregateRootBehaviour;

    private(set) ?BillingAddress $billingAddress = null;

    private(set) TokenizedSourceInterface $source;

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

    public function decline(string $reason): static
    {
        $this->isValid() || throw TokenException::suspended();

        $this->recordThat(new TokenDeclined($reason));

        return $this;
    }

    public function use(?callable $callback = null): static
    {
        $this->isValid() || throw TokenException::suspended();

        isset($callback) && $callback($this);
        $this->recordThat(new TokenUsed());

        return $this;
    }

    public static function create(CreateTokenCommandInterface $command): static
    {
        $command->card->expired() && throw CardException::expired();

        $self = new static($command->id);
        $self->recordThat(new TokenCreated($command->card, $command->billingAddress));

        return $self;
    }

    protected function apply(object $event): void
    {
        switch ($event::class) {
            case TokenCreated::class:
                $this->applyTokenCreated($event);
                return;
            case TokenDeclined::class:
                $this->applyTokenDeclined($event);
                return;
            case TokenUsed::class:
                $this->applyTokenUsed();
                return;
        }
    }

    // Event Listeners
    protected function applyTokenCreated(TokenCreated $event): void
    {
        $this->source = $event->source;
        $this->billingAddress = $event->billingAddress;
        $this->status = TokenStatusEnum::PENDING;
    }

    protected function applyTokenDeclined(TokenDeclined $event): void
    {
        $this->status = TokenStatusEnum::DECLINED;
        $this->declineReason = $event->reason;
    }

    protected function applyTokenUsed(): void
    {
        $this->status = TokenStatusEnum::USED;
    }
}