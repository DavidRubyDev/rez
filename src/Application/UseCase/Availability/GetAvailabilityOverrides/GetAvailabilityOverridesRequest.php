<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailabilityOverrides;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class GetAvailabilityOverridesRequest
{
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
    ) {
    }
}
