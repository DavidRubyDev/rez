<?php

declare(strict_types=1);

namespace Rez\Domain\Availability;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class AvailabilityRule
{
    public function __construct(
        private readonly ResourceId $resourceId,
        private readonly int $dayOfWeek,
        private readonly string $openTime,
        private readonly string $closeTime,
    ) {
        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            throw new \InvalidArgumentException('Day of week must be between 0 (Sunday) and 6 (Saturday).');
        }

        if ($closeTime <= $openTime) {
            throw new \InvalidArgumentException('Close time must be after open time.');
        }
    }

    public function resourceId(): ResourceId
    {
        return $this->resourceId;
    }

    public function dayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function openTime(): string
    {
        return $this->openTime;
    }

    public function closeTime(): string
    {
        return $this->closeTime;
    }

    public function appliesToDate(DateTimeImmutable $date): bool
    {
        return (int) $date->format('w') === $this->dayOfWeek;
    }

    public function openTimeForDate(DateTimeImmutable $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date->format('Y-m-d') . ' ' . $this->openTime);
    }

    public function closeTimeForDate(DateTimeImmutable $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date->format('Y-m-d') . ' ' . $this->closeTime);
    }
}
