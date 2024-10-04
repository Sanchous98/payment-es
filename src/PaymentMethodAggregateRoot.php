<?php

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRootWithAggregates;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use PaymentSystem\Commands\CreatePaymentMethodCommandInterface;
use PaymentSystem\Commands\CreateTokenPaymentMethodCommandInterface;
use PaymentSystem\Commands\UpdatedPaymentMethodCommandInterface;
use PaymentSystem\Contracts\SourceInterface;
use PaymentSystem\Enum\PaymentMethodStatusEnum;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodFailed;
use PaymentSystem\Events\PaymentMethodSuspended;
use PaymentSystem\Events\PaymentMethodUpdated;
use PaymentSystem\Exceptions\TokenExpiredException;
use PaymentSystem\Gateway\Events\GatewayPaymentMethodAdded;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;
use PaymentSystem\ValueObjects\State;
use RuntimeException;

class PaymentMethodAggregateRoot implements AggregateRootWithSnapshotting, TenderInterface
{
    use SnapshotBehaviour;
    use AggregateRootWithAggregates;

    private BillingAddress $billingAddress;

    private SourceInterface $source;

    private PaymentMethodStatusEnum $status;

    private Gateway\PaymentMethodsAggregate $gateway;

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

    public function is(PaymentMethodStatusEnum $status): bool
    {
        return $this->status === $status;
    }

    public function getGatewayPaymentMethods(): Gateway\PaymentMethodsAggregate
    {
        return $this->gateway;
    }

    public function getGatewayTenders(): array
    {
        return array_merge(...array_values($this->gateway->getPaymentMethods()));
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

    public function use(callable $callback = null): static
    {
        if (!$this->isValid()) {
            throw new RuntimeException('Payment method is not valid');
        }

        isset($callback) && $callback($this);

        return $this;
    }

    protected function applyPaymentMethodCreated(PaymentMethodCreated $event): void
    {
        $this->billingAddress = $event->billingAddress;
        $this->source = $event->source;
        $this->status = PaymentMethodStatusEnum::PENDING;

        $this->gateway = new Gateway\PaymentMethodsAggregate($this->eventRecorder());
        $this->registerAggregate($this->gateway);
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

    protected function applyGatewayPaymentMethodAdded(GatewayPaymentMethodAdded $event): void
    {
        $this->status = PaymentMethodStatusEnum::SUCCEEDED;
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

    public function __sleep()
    {
        unset($this->eventRecorder);
        return array_keys((array)$this);
    }
}