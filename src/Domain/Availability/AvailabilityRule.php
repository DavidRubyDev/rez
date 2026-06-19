<?php

declare(strict_types=1);

namespace Rez\Domain\Availability;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class AvailabilityRule
{
    public function __construct(
        private readonly ResourceId $resourceId,
        private readonly DayOfWeek $dayOfWeek,
        private readonly string $openTime,
        private readonly string $closeTime,
    ) {
        if ($closeTime <= $openTime) {
            throw new \InvalidArgumentException('Close time must be after open time.');
        }
    }

    public function getResourceId(): ResourceId
    {
        return $this->resourceId;
    }

    public function getDayOfWeek(): DayOfWeek
    {
        return $this->dayOfWeek;
    }

    public function getOpenTime(): string
    {
        return $this->openTime;
    }

    public function getCloseTime(): string
    {
        return $this->closeTime;
    }

    public function appliesToDate(DateTimeImmutable $date): bool
    {
        return DayOfWeek::fromDate($date) === $this->dayOfWeek;
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
