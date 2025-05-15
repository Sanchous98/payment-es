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

    private ?BillingAddress $billingAddress = null;

    private TokenizedSourceInterface $source;

    private TokenStatusEnum $status;

    private string $declineReason = '';

    public function isPending(): bool
    {
        return $this->status === TokenStatusEnum::PENDING;
    }

    public function isUsed(): bool
    {
        return $this->status === TokenStatusEnum::USED;
    }

    public function isDeclined(): bool
    {
        return $this->status === TokenStatusEnum::DECLINED;
    }

    public function isValid(): bool
    {
        return $this->status === TokenStatusEnum::VALID;
    }

    public function getSource(): TokenizedSourceInterface
    {
        return $this->source;
    }

    public function getBillingAddress(): ?BillingAddress
    {
        return $this->billingAddress;
    }

    public function getDeclineReason(): string
    {
        return $this->declineReason;
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
        $command->getCard()->expired() && throw CardException::expired();

        $self = new static($command->getId());
        $self->recordThat(new TokenCreated($command->getCard(), $command->getBillingAddress()));

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