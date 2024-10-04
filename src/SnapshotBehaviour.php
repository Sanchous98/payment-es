<?php

namespace PaymentSystem;

use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use Generator;

trait SnapshotBehaviour
{
    public static function reconstituteFromSnapshotAndEvents(Snapshot $snapshot, Generator $events): static
    {
        $self = self::createNewInstance($snapshot->aggregateRootId());
        $self->applySnapshot($snapshot);

        foreach ($events as $event) {
            $self->apply($event);
        }

        $self->aggregateRootVersion = $events->getReturn() ?: 0;

        return $self;
    }

    abstract protected function applySnapshot(Snapshot $snapshot): void;

    public function createSnapshot(): Snapshot
    {
        return new Snapshot($this->aggregateRootId(), $this->aggregateRootVersion(), $this->createSnapshotState());
    }

    abstract public function aggregateRootId(): AggregateRootId;

    abstract public function aggregateRootVersion(): int;

    abstract protected function createSnapshotState(): mixed;
}