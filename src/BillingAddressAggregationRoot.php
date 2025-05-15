<?php

declare(strict_types=1);

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use PaymentSystem\Commands\CreateBillingAddressCommandInterface;
use PaymentSystem\Commands\UpdateBillingAddressCommandInterface;
use PaymentSystem\Entities\BillingAddress;
use PaymentSystem\Events\BillingAddressCreated;
use PaymentSystem\Events\BillingAddressDeleted;
use PaymentSystem\Events\BillingAddressUpdated;

class BillingAddressAggregationRoot implements AggregateRoot
{
    use AggregateRootBehaviour;

    private(set) BillingAddress $address;

    public static function create(CreateBillingAddressCommandInterface $command): static
    {
        $self = new static($command->id);
        $self->recordThat(
            new BillingAddressCreated(
                $command->firstName,
                $command->lastName,
                $command->city,
                $command->country,
                $command->postalCode,
                $command->email,
                $command->phoneNumber,
                $command->addressLine,
                $command->addressLineExtra,
                $command->state,
            )
        );

        return $self;
    }

    public function update(UpdateBillingAddressCommandInterface $command): static
    {
        $this->recordThat(
            new BillingAddressUpdated(
                $command->firstName,
                $command->lastName,
                $command->city,
                $command->country,
                $command->postalCode,
                $command->email,
                $command->phoneNumber,
                $command->addressLine,
                $command->addressLineExtra,
                $command->state,
            )
        );

        return $this;
    }

    public function delete(): static
    {
        $this->recordThat(new BillingAddressDeleted());

        return $this;
    }

    protected function applyBillingAddressCreated(BillingAddressCreated $event): void
    {
        $this->address = new BillingAddress(
            $this->aggregateRootId(),
            $event->firstName,
            $event->lastName,
            $event->city,
            $event->country,
            $event->postalCode,
            $event->email,
            $event->phone,
            $event->addressLine,
            $event->addressLineExtra,
            $event->state,
        );
    }

    protected function applyBillingAddressUpdated(BillingAddressUpdated $event): void
    {
        $this->address->update(
            $event->firstName,
            $event->lastName,
            $event->city,
            $event->country,
            $event->postalCode,
            $event->email,
            $event->phone,
            $event->addressLine,
            $event->addressLineExtra,
            $event->state
        );;
    }

    protected function applyBillingAddressPlanDeleted(): void
    {
        unset($this->address);
    }
}