<?php

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use PaymentSystem\Commands\CreateTokenCommandInterface;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Enum\TokenStatusEnum;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Events\TokenDeclined;
use PaymentSystem\Events\TokenUsed;
use PaymentSystem\Exceptions\CardExpiredException;
use PaymentSystem\Exceptions\TokenExpiredException;
use PaymentSystem\ValueObjects\CreditCard;

class TokenAggregateRoot implements AggregateRootWithSnapshotting, TenderInterface
{
    use AggregateRootBehaviour;
    use SnapshotBehaviour;

    private CreditCard $card;

    private TokenStatusEnum $status;

    private string $declineReason = '';

    public function isValid(): bool
    {
        return $this->status === TokenStatusEnum::CREATED;
    }

    public function isUsed(): bool
    {
        return $this->status === TokenStatusEnum::USED;
    }

    public function isDeclined(): bool
    {
        return $this->status === TokenStatusEnum::DECLINED;
    }

    public function getCard(): CreditCard
    {
        return $this->card;
    }

    public function getSource(): SourceInterface
    {
        return $this->card;
    }

    public function getDeclineReason(): string
    {
        return $this->declineReason;
    }

    public static function create(CreateTokenCommandInterface $command): static
    {
        $command->getCard()->expired() && throw new CardExpiredException();

        $self = new static($command->getId());
        $self->recordThat(new TokenCreated($command->getCard()));

        return $self;
    }

    public function decline(string $reason): self
    {
        if (!$this->isValid()) {
            throw new TokenExpiredException();
        }

        $this->recordThat(new TokenDeclined($reason));

        return $this;
    }

    public function use(): static
    {
        if (!$this->isValid()) {
            throw new TokenExpiredException();
        }

        $this->recordThat(new TokenUsed());

        return $this;
    }

    protected function applyTokenUsed(): void
    {
        $this->status = TokenStatusEnum::USED;
    }

    protected function applyTokenCreated(TokenCreated $event): void
    {
        $this->card = $event->card;
        $this->status = TokenStatusEnum::CREATED;
    }

    protected function applyTokenDeclined(TokenDeclined $event): void
    {
        $this->status = TokenStatusEnum::DECLINED;
        $this->declineReason = $event->reason;
    }

    protected function apply(object $event): void
    {
        switch ($event::class) {
            case TokenCreated::class:
                $this->applyTokenCreated($event);
                break;
            case TokenDeclined::class:
                $this->applyTokenDeclined($event);
                break;
            case TokenUsed::class:
                $this->applyTokenUsed();
                break;
        }
    }

    protected function applySnapshot(Snapshot $snapshot): void
    {
        $this->card = CreditCard::fromArray($snapshot->state()['card']);
        $this->status = $snapshot->state()['status'];
    }

    protected function createSnapshotState(): array
    {
        return [
            'card' => $this->card,
            'status' => $this->status,
        ];
    }
}