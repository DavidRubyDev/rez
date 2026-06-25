<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\DeleteAvailabilityOverride;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class DeleteAvailabilityOverrideRequest
{
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly DateTimeImmutable $date,
    ) {
    }
}
