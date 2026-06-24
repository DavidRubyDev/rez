<?php

declare(strict_types=1);

namespace Rez\Domain\Reservation;

use DateInterval;
use DateTimeImmutable;
use Rez\Domain\Exception\InvalidTimeSlotException;

final class TimeSlot
{
    /**
     * @throws InvalidTimeSlotException
     */
    public function __construct(
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
    ) {
        if ($end <= $start) {
            throw new InvalidTimeSlotException(
                sprintf('Slot end (%s) must be after start (%s).', $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s'))
            );
        }
    }

    public function overlapsWith(TimeSlot $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    public function duration(): \DateInterval
    {
        return $this->start->diff($this->end);
    }

    public function equals(TimeSlot $other): bool
    {
        return $this->start->getTimestamp() === $other->start->getTimestamp()
            && $this->end->getTimestamp() === $other->end->getTimestamp();
    }

    public function __toString(): string
    {
        return $this->start->format('Y-m-d H:i:s') . ' / ' . $this->end->format('Y-m-d H:i:s');
    }
}
