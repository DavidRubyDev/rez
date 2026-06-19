<?php

declare(strict_types=1);

namespace Rez\Domain\Availability;

use DateTimeImmutable;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;

final class AvailabilityWindow
{
    /** @param TimeSlot[] $availableSlots */
    public function __construct(
        private readonly ResourceId $resourceId,
        private readonly DateTimeImmutable $date,
        private readonly array $availableSlots,
    ) {
    }

    public static function empty(ResourceId $resourceId, DateTimeImmutable $date): self
    {
        return new self($resourceId, $date, []);
    }

    public function getResourceId(): ResourceId
    {
        return $this->resourceId;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    /** @return TimeSlot[] */
    public function getSlots(): array
    {
        return $this->availableSlots;
    }

    public function isEmpty(): bool
    {
        return $this->availableSlots === [];
    }

    public function count(): int
    {
        return count($this->availableSlots);
    }
}
