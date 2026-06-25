<?php

declare(strict_types=1);

namespace Rez\Domain\Availability;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class AvailabilityRule
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly DayOfWeek $dayOfWeek,
        public readonly string $openTime,
        public readonly string $closeTime,
    ) {
        if ($closeTime <= $openTime) {
            throw new \InvalidArgumentException('Close time must be after open time.');
        }
    }

    public function appliesToDate(DateTimeImmutable $date): bool
    {
        return DayOfWeek::fromDate($date) === $this->dayOfWeek;
    }

    public function openTimeForDate(DateTimeImmutable $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date->format('Y-m-d') . ' ' . $this->openTime, new \DateTimeZone('UTC'));
    }

    public function closeTimeForDate(DateTimeImmutable $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date->format('Y-m-d') . ' ' . $this->closeTime, new \DateTimeZone('UTC'));
    }
}
