<?php

declare(strict_types=1);

namespace PaymentSystem\ValueObjects;

use DateInterval;
use DateTimeImmutable;

class RecurringActionTracker
{
    private int $count = 0;

    private readonly DateTimeImmutable $start;

    public function __construct(
        private readonly DateInterval $interval,
        DateTimeImmutable $start = new DateTimeImmutable(),
        private DateTimeImmutable $prev = new DateTimeImmutable(),
    ) {
        $this->start = $start->setTime(0, 0);
    }

    public function advance(DateTimeImmutable $when): self
    {
        ++$this->count;
        $this->prev = $when->setTime(0, 0);
        
        return $this;
    }

    public function getStartDate(): DateTimeImmutable
    {
        return $this->start;
    }

    public function countActions(): int
    {
        return $this->count;
    }

    public function getEndDate(): DateTimeImmutable
    {
        return $this->prev->add($this->interval);
    }
}