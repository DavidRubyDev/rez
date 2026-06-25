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
        public readonly ?DateTimeImmutable $validFrom = null,
        public readonly ?DateTimeImmutable $validUntil = null,
    ) {
        if ($closeTime <= $openTime) {
            throw new \InvalidArgumentException('Close time must be after open time.');
        }
    }

    public function appliesToDate(DateTimeImmutable $date): bool
    {
        return DayOfWeek::fromDate($date) === $this->dayOfWeek;
    }

    public function isActiveOn(DateTimeImmutable $date): bool
    {
        $utc = new \DateTimeZone('UTC');
        $day = new DateTimeImmutable($date->format('Y-m-d'), $utc);

        if ($this->validFrom !== null) {
            $from = new DateTimeImmutable($this->validFrom->format('Y-m-d'), $utc);
            if ($day < $from) {
                return false;
            }
        }

        if ($this->validUntil !== null) {
            $until = new DateTimeImmutable($this->validUntil->format('Y-m-d'), $utc);
            if ($day > $until) {
                return false;
            }
        }

        return true;
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
