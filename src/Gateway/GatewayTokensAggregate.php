<?php

namespace PaymentSystem\Gateway;

use EventSauce\EventSourcing\AggregateAppliesKnownEvents;
use EventSauce\EventSourcing\EventRecorder;
use EventSauce\EventSourcing\EventSourcedAggregate;
use PaymentSystem\Events\GatewayTokenAdded;
use PaymentSystem\Events\GatewayTokenDeclined;

class GatewayTokensAggregate implements EventSourcedAggregate
{
    use AggregateAppliesKnownEvents;
    
    public function __construct(private readonly EventRecorder $recorder)
    {
    }

    public function addToken(GatewayTokenInterface $token): self
    {
        if ($token->isValid()) {
            $this->recorder->recordThat(new GatewayTokenAdded($token->getRawResponse()));
        } else {
            $this->recorder->recordThat(new GatewayTokenDeclined());
        }

        return $this;
    }
}