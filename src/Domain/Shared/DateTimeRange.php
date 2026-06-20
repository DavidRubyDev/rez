<?php

declare(strict_types=1);

namespace Rez\Domain\Shared;

use DateTimeImmutable;
use Rez\Domain\Reservation\TimeSlot;

final class DateTimeRange
{
    public function __construct(
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
    ) {
        if ($end < $start) {
            throw new \InvalidArgumentException('End must not be before start.');
        }
    }

    public function contains(DateTimeImmutable $point): bool
    {
        return $point >= $this->start && $point <= $this->end;
    }

    public function overlapsWith(DateTimeRange $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    public function toTimeSlot(): TimeSlot
    {
        return new TimeSlot($this->start, $this->end);
    }
}
