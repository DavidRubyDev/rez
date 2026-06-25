<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use DateTimeImmutable;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Resource\ResourceId;

interface AvailabilityRepositoryInterface
{
    /**
     * @return AvailabilityRule[]
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findRulesForResource(ResourceId $resourceId): array;

    /**
     * @return AvailabilityOverride[]
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findOverridesForResource(ResourceId $resourceId, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function saveRule(AvailabilityRule $rule): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function deleteRule(ResourceId $resourceId, DayOfWeek $dayOfWeek): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function saveOverride(AvailabilityOverride $override): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function deleteOverride(ResourceId $resourceId, DateTimeImmutable $date): void;
}
