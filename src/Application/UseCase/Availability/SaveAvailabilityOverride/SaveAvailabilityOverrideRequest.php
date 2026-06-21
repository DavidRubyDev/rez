<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityOverride;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class SaveAvailabilityOverrideRequest
{
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly DateTimeImmutable $date,
        public readonly bool $available,
    ) {
    }
}
