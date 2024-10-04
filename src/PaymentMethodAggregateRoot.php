<?php

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use PaymentSystem\Commands\CreatePaymentMethodCommandInterface;
use PaymentSystem\Commands\CreateTokenPaymentMethodCommandInterface;
use PaymentSystem\Commands\UpdatedPaymentMethodCommandInterface;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Enum\PaymentMethodStatusEnum;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodFailed;
use PaymentSystem\Events\PaymentMethodSucceeded;
use PaymentSystem\Events\PaymentMethodSuspended;
use PaymentSystem\Events\PaymentMethodUpdated;
use PaymentSystem\Exceptions\TokenExpiredException;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;
use PaymentSystem\ValueObjects\State;
use RuntimeException;

class PaymentMethodAggregateRoot implements AggregateRootWithSnapshotting, TenderInterface
{
    use SnapshotBehaviour;
    use AggregateRootBehaviour;

    private BillingAddress $billingAddress;

    private SourceInterface $source;

    private PaymentMethodStatusEnum $status;

    public function is(PaymentMethodStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public function getBillingAddress(): BillingAddress
    {
        return $this->billingAddress;
    }

    public function getSource(): SourceInterface
    {
        return $this->source;
    }

    public function isValid(): bool
    {
        return $this->is(PaymentMethodStatusEnum::SUCCEEDED);
    }

    public static function create(CreatePaymentMethodCommandInterface $command): static
    {
        $self = new static($command->getId());
        $self->recordThat(new PaymentMethodCreated($command->getBillingAddress(), $command->getSource()));

        return $self;
    }

    public static function createFromToken(CreateTokenPaymentMethodCommandInterface $command): static
    {
        $command->getToken()->isUsed() && throw new TokenExpiredException();

        $self = new static($command->getId());
        $self->recordThat(
            new PaymentMethodCreated(
                $command->getBillingAddress(),
                $command->getToken()->getCard(),
                $command->getToken()->aggregateRootId()
            )
        );

        return $self;
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

    protected function apply(object $event): void
    {
        switch ($event::class) {
            case PaymentMethodCreated::class:
                $this->applyPaymentMethodCreated($event);
                break;
            case PaymentMethodUpdated::class:
                $this->applyPaymentMethodUpdated($event);
                break;
            case PaymentMethodSucceeded::class:
                $this->applyPaymentMethodSucceeded();
                break;
            case PaymentMethodSuspended::class:
                $this->applyPaymentMethodSuspended();
                break;
            case PaymentMethodFailed::class:
                $this->applyPaymentMethodFailed();
                break;
        }

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
            state: isset($snapshot->state()['billingAddress']['state']) ? new State(
                $snapshot->state()['billingAddress']['address_line_extra']
            ) : null,
        );
        $this->source = $snapshot->state()['source'];
    }

    protected function createSnapshotState(): array
    {
        return [
            'billingAddress' => $this->billingAddress->jsonSerialize(),
            'source' => $this->source,
            'status' => $this->status->value,
        ];
    }
}