<?php

declare(strict_types=1);

namespace Rez\Domain\Shared;

use DateTimeImmutable;
use DateTimeZone;

final class Utc
{
    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::timezone());
    }
}
