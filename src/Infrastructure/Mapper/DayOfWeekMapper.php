<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Mapper;

use Rez\Domain\Availability\DayOfWeek;

final class DayOfWeekMapper
{
    public function toString(DayOfWeek $day): string
    {
        return match($day) {
            DayOfWeek::Monday    => 'monday',
            DayOfWeek::Tuesday   => 'tuesday',
            DayOfWeek::Wednesday => 'wednesday',
            DayOfWeek::Thursday  => 'thursday',
            DayOfWeek::Friday    => 'friday',
            DayOfWeek::Saturday  => 'saturday',
            DayOfWeek::Sunday    => 'sunday',
        };
    }

    public function fromString(string $day): DayOfWeek
    {
        return match($day) {
            'monday'    => DayOfWeek::Monday,
            'tuesday'   => DayOfWeek::Tuesday,
            'wednesday' => DayOfWeek::Wednesday,
            'thursday'  => DayOfWeek::Thursday,
            'friday'    => DayOfWeek::Friday,
            'saturday'  => DayOfWeek::Saturday,
            'sunday'    => DayOfWeek::Sunday,
            default     => throw new \InvalidArgumentException("Unknown day of week: '{$day}'."),
        };
    }
}
