<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use DateTimeImmutable;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Resource\ResourceId;

interface AvailabilityRepositoryInterface
{
    /** @return AvailabilityRule[] */
    public function findRulesForResource(ResourceId $resourceId): array;

    /** @return AvailabilityOverride[] */
    public function findOverridesForResource(ResourceId $resourceId, DateTimeImmutable $from, DateTimeImmutable $to): array;
}
