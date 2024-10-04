<?php

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use PaymentSystem\Commands\CreatePaymentMethodCommandInterface;
use PaymentSystem\Commands\UpdatedPaymentMethodCommandInterface;
use PaymentSystem\Enum\PaymentMethodStatusEnum;
use PaymentSystem\Enum\SourceEnum;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodFailed;
use PaymentSystem\Events\PaymentMethodSucceeded;
use PaymentSystem\Events\PaymentMethodSuspended;
use PaymentSystem\Events\PaymentMethodUpdated;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;
use PaymentSystem\ValueObjects\Source;
use PaymentSystem\ValueObjects\State;
use RuntimeException;

class PaymentMethodAggregateRoot implements AggregateRootWithSnapshotting
{
    use SnapshotBehaviour;
    use AggregateRootBehaviour;

    private BillingAddress $billingAddress;

    private Source $source;

    private PaymentMethodStatusEnum $status;

    public function is(PaymentMethodStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public function getBillingAddress(): BillingAddress
    {
        return $this->billingAddress;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function create(CreatePaymentMethodCommandInterface $command): static
    {
        if ($command->getSource()->unwrap()->getType() === SourceEnum::CARD) {
            $command->getSource()->unwrap()->expired() && throw new RuntimeException('Expired card');
        }

        $this->recordThat(new PaymentMethodCreated($command->getBillingAddress(), $command->getSource()));

        return $this;
    }

    public function update(UpdatedPaymentMethodCommandInterface $command): static
    {
        if ($this->status === PaymentMethodStatusEnum::FAILED || $this->status === PaymentMethodStatusEnum::SUSPENDED) {
            throw new RuntimeException('Payment method already suspended or failed');
        }

        $this->recordThat(new PaymentMethodUpdated($command->getBillingAddress()));

        return $this;
    }

    public function success(): static
    {
        if ($this->status !== PaymentMethodStatusEnum::PENDING) {
            throw new RuntimeException('Payment method is not pending to creating');
        }

        $this->recordThat(new PaymentMethodSucceeded());

        return $this;
    }

    public function fail(): static
    {
        if ($this->status !== PaymentMethodStatusEnum::PENDING) {
            throw new RuntimeException('Payment method is not pending to creating');
        }

        $this->recordThat(new PaymentMethodFailed());

        return $this;
    }

    public function suspend(): static
    {
        if ($this->status !== PaymentMethodStatusEnum::SUCCEEDED) {
            throw new RuntimeException('Payment method is not succeeded to be suspended');
        }

        $this->recordThat(new PaymentMethodSuspended());

        return $this;
    }

    protected function applyPaymentMethodCreated(PaymentMethodCreated $event): void
    {
        $this->billingAddress = $event->billingAddress;
        $this->source = $event->source;
        $this->status = PaymentMethodStatusEnum::PENDING;
    }

    protected function applyPaymentMethodUpdated(PaymentMethodUpdated $event): void
    {
        $this->billingAddress = $event->billingAddress;
    }

    protected function applyPaymentMethodSucceeded(): void
    {
        $this->status = PaymentMethodStatusEnum::SUCCEEDED;
    }

    protected function applyPaymentMethodSuspended(): void
    {
        $this->status = PaymentMethodStatusEnum::SUSPENDED;
    }

    protected function applyPaymentMethodFailed(): void
    {
        $this->status = PaymentMethodStatusEnum::FAILED;
    }

    protected function apply(PaymentMethodCreated|PaymentMethodUpdated|PaymentMethodSuspended|PaymentMethodSucceeded|PaymentMethodFailed $event): void
    {
        match ($event::class) {
            PaymentMethodCreated::class => $this->applyPaymentMethodCreated($event),
            PaymentMethodUpdated::class => $this->applyPaymentMethodUpdated($event),
            PaymentMethodSucceeded::class => $this->applyPaymentMethodSucceeded(),
            PaymentMethodSuspended::class => $this->applyPaymentMethodSuspended(),
            PaymentMethodFailed::class => $this->applyPaymentMethodFailed(),
        };

        ++$this->aggregateRootVersion;
    }

    protected function applySnapshot(Snapshot $snapshot): void
    {
        $this->aggregateRootVersion = $snapshot->aggregateRootVersion();
        $this->billingAddress = new BillingAddress(
            firstName: $snapshot->state()['billingAddress']['first_name'],
            lastName: $snapshot->state()['billingAddress']['last_name'],
            city: $snapshot->state()['billingAddress']['city'],
            country: new Country($snapshot->state()['billingAddress']['country']),
            postalCode: $snapshot->state()['billingAddress']['postal_code'],
            email: new Email($snapshot->state()['billingAddress']['email']),
            phone: new PhoneNumber($snapshot->state()['billingAddress']['phone']),
            addressLine: $snapshot->state()['billingAddress']['address_line'],
            addressLineExtra: $snapshot->state()['billingAddress']['address_line_extra'] ?? '',
            state: isset($snapshot->state()['billingAddress']['state']) ? new State($snapshot->state()['billingAddress']['address_line_extra']) : null,
        );
        $this->source = Source::fromArray(
            SourceEnum::from($snapshot->state()['source']['type']),
            $snapshot->state()['source'][$snapshot->state()['source']['type']]
        );
    }

    protected function createSnapshotState(): array
    {
        return [
            'billingAddress' => $this->billingAddress->jsonSerialize(),
            'source' => $this->source->jsonSerialize(),
            'status' => $this->status->value,
        ];
    }
}