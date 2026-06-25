<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\DeleteAvailabilityRule;

use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Resource\ResourceId;

final class DeleteAvailabilityRuleRequest
{
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly DayOfWeek $dayOfWeek,
    ) {
    }
}
