<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\AggregateRootWithAggregates;
use PaymentSystem\Commands\CreateTokenCommandInterface;
use PaymentSystem\Contracts\TokenizedSourceInterface;
use PaymentSystem\Enum\TokenStatusEnum;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Events\TokenDeclined;
use PaymentSystem\Events\TokenUsed;
use PaymentSystem\Exceptions\CardExpiredException;
use PaymentSystem\Exceptions\TokenExpiredException;
use PaymentSystem\Gateway\Events\GatewayTokenAdded;
use PaymentSystem\ValueObjects\BillingAddress;

class TokenAggregateRoot implements AggregateRoot, TenderInterface
{
    use AggregateRootWithAggregates {
        __construct as private __aggregateRootConstruct;
    }

    private ?BillingAddress $billingAddress = null;

    private TokenizedSourceInterface $source;

    private TokenStatusEnum $status;

    private string $declineReason = '';

    private Gateway\TokensAggregate $gateway;

    private function __construct(AggregateRootId $id)
    {
        $this->__aggregateRootConstruct($id);
        $this->gateway = new Gateway\TokensAggregate($this->eventRecorder());
        $this->registerAggregate($this->gateway);
    }

    public static function create(CreateTokenCommandInterface $command): static
    {
        $command->getCard()->expired() && throw new CardExpiredException();

        $self = new static($command->getId());
        $self->recordThat(new TokenCreated($command->getCard(), $command->getBillingAddress()));

        return $self;
    }

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

    public function getGatewayTokens(): Gateway\TokensAggregate
    {
        return $this->gateway;
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
        $this->isValid() || throw new TokenExpiredException();

        $this->recordThat(new TokenDeclined($reason));

        return $this;
    }

    public function use(?callable $callback = null): static
    {
        $this->isValid() || throw new TokenExpiredException();

        isset($callback) && $callback($this);
        $this->recordThat(new TokenUsed());

        return $this;
    }

    public function getGatewayTenders(): array
    {
        return array_merge(...array_values($this->gateway->getTokens()));
    }

    public function __sleep()
    {
        unset($this->eventRecorder);
        return array_keys((array)$this);
    }

    public function __wakeup(): void
    {
        foreach ($this->aggregatesInsideRoot as $aggregate) {
            $aggregate->__construct($this->eventRecorder());
        }
    }

    // Event Listeners

    protected function applyTokenCreated(TokenCreated $event): void
    {
        $this->source = $event->source;
        $this->billingAddress = $event->billingAddress;
        $this->status = TokenStatusEnum::PENDING;

        $this->gateway = new Gateway\TokensAggregate($this->eventRecorder());
        $this->registerAggregate($this->gateway);
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

    protected function applyGatewayTokenAdded(GatewayTokenAdded $event): void
    {
        if (!isset($this->source)) {
            $this->source = $event->token->getSource();
        }

        if (!isset($this->billingAddress)) {
            $this->billingAddress = $event->token->getBillingAddress();
        }
        $this->status = TokenStatusEnum::VALID;
    }
}