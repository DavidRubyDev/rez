<?php

declare(strict_types=1);

namespace Rez\Domain\Availability;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class AvailabilityOverride
{
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly DateTimeImmutable $date,
        public readonly bool $isAvailable,
    ) {
    }
}
