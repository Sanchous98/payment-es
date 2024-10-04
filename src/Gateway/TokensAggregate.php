<?php

namespace PaymentSystem\Gateway;

use EventSauce\EventSourcing\AggregateAppliesKnownEvents;
use EventSauce\EventSourcing\EventRecorder;
use EventSauce\EventSourcing\EventSourcedAggregate;
use PaymentSystem\Gateway\Resources\TokenInterface;

class TokensAggregate implements EventSourcedAggregate
{
    use AggregateAppliesKnownEvents;

    private array $tokens = [];

    public function __construct(private EventRecorder $eventRecorder)
    {
    }

    /**
     * @param callable(self): TokenInterface $callback
     */
    public function add(callable $callback): static
    {
        $token = $callback($this);
        assert($token instanceof TokenInterface);
        $token->isValid(); // TODO: throw

        $this->eventRecorder->recordThat(new Events\GatewayTokenAdded($token));

        return $this;
    }

    protected function applyGatewayTokenAdded(Events\GatewayTokenAdded $event): void
    {
        $this->tokens[$event->token->getGatewayId()->toString()][$event->token->getId()->toString()] = $event->token;
    }

    /**
     * @param callable(TokenInterface): bool $callback
     * @return TokenInterface|null
     */
    public function find(callable $callback): ?TokenInterface
    {
        foreach ($this->tokens as $token) {
            if ($callback($token)) {
                return $token;
            }
        }

        return null;
    }

    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function __sleep(): array
    {
        unset($this->eventRecorder);
        return array_keys((array)$this);
    }
}