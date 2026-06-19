<?php

declare(strict_types=1);

namespace Rez\Domain\Shared;

use DateTimeImmutable;
use Rez\Domain\Reservation\TimeSlot;

final class DateTimeRange
{
    public function __construct(
        private readonly DateTimeImmutable $start,
        private readonly DateTimeImmutable $end,
    ) {
        if ($end < $start) {
            throw new \InvalidArgumentException('End must not be before start.');
        }
    }

    public function getStart(): DateTimeImmutable
    {
        return $this->start;
    }

    public function getEnd(): DateTimeImmutable
    {
        return $this->end;
    }

    public function contains(DateTimeImmutable $point): bool
    {
        return $point >= $this->start && $point <= $this->end;
    }

    public function overlapsWith(DateTimeRange $other): bool
    {
        return $this->start < $other->getEnd() && $this->end > $other->getStart();
    }

    public function toTimeSlot(): TimeSlot
    {
        return new TimeSlot($this->start, $this->end);
    }
}
