<?php

declare(strict_types=1);

namespace Rez\Domain\Availability;

use DateTimeImmutable;

enum DayOfWeek
{
    case Monday;
    case Tuesday;
    case Wednesday;
    case Thursday;
    case Friday;
    case Saturday;
    case Sunday;

    public static function fromDate(DateTimeImmutable $date): self
    {
        return match((int) $date->format('N')) {
            1 => self::Monday,
            2 => self::Tuesday,
            3 => self::Wednesday,
            4 => self::Thursday,
            5 => self::Friday,
            6 => self::Saturday,
            7 => self::Sunday,
        };
    }
}
