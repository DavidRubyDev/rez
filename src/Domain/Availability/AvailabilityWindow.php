<?php

declare(strict_types=1);

namespace Rez\Domain\Availability;

use DateTimeImmutable;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;

final class AvailabilityWindow
{
    /** @param list<TimeSlot> $slots */
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly DateTimeImmutable $date,
        public readonly array $slots,
    ) {
    }

    public static function empty(ResourceId $resourceId, DateTimeImmutable $date): self
    {
        return new self($resourceId, $date, []);
    }

    public function isEmpty(): bool
    {
        return $this->slots === [];
    }

    public function count(): int
    {
        return count($this->slots);
    }
}
