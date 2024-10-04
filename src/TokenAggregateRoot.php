<?php

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRootWithAggregates;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use PaymentSystem\Commands\CreateTokenCommandInterface;
use PaymentSystem\Contracts\TokenizedSourceInterface;
use PaymentSystem\Enum\TokenStatusEnum;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Events\TokenDeclined;
use PaymentSystem\Events\TokenUsed;
use PaymentSystem\Exceptions\CardExpiredException;
use PaymentSystem\Exceptions\TokenExpiredException;
use PaymentSystem\ValueObjects\CreditCard;

class TokenAggregateRoot implements AggregateRootWithSnapshotting, TenderInterface
{
    use AggregateRootWithAggregates;
    use SnapshotBehaviour;

    /** @todo Should be TokenizedSourceInterface */
    private CreditCard $card;

    private TokenStatusEnum $status;

    private string $declineReason = '';

    private Gateway\TokensAggregate $gateway;

    public static function create(CreateTokenCommandInterface $command): static
    {
        $command->getCard()->expired() && throw new CardExpiredException();

        $self = new static($command->getId());
        $self->recordThat(new TokenCreated($command->getCard()));

        return $self;
    }

    public function getCard(): CreditCard
    {
        return $this->card;
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
        return $this->card;
    }

    public function getDeclineReason(): string
    {
        return $this->declineReason;
    }

    public function decline(string $reason): self
    {
        if (!$this->isValid()) {
            throw new TokenExpiredException();
        }

        $this->recordThat(new TokenDeclined($reason));

        return $this;
    }

    public function use(callable $callback = null): static
    {
        if (!$this->isValid()) {
            throw new TokenExpiredException();
        }

        isset($callback) && $callback($this);
        $this->recordThat(new TokenUsed());

        return $this;
    }

    public function getGatewayTenders(): array
    {
        return array_merge(...array_values($this->gateway->getTokens()));
    }

    protected function applyTokenCreated(TokenCreated $event): void
    {
        $this->card = $event->source;
        $this->status = TokenStatusEnum::CREATED;

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

    protected function applyGatewayTokenAdded(): void
    {
        $this->status = TokenStatusEnum::VALID;
    }

    protected function applySnapshot(Snapshot $snapshot): void
    {
        $this->card = CreditCard::fromArray($snapshot->state()['source']);
        $this->status = $snapshot->state()['status'];
    }

    protected function createSnapshotState(): array
    {
        return [
            'source' => $this->card,
            'status' => $this->status,
        ];
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
}