<?php

declare(strict_types=1);

namespace Rez\Domain\Availability;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class AvailabilityOverride
{
    public function __construct(
        private readonly ResourceId $resourceId,
        private readonly DateTimeImmutable $date,
        private readonly bool $available,
    ) {
    }

    public function resourceId(): ResourceId
    {
        return $this->resourceId;
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }
}
