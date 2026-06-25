<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityRule;

use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Resource\ResourceId;

final class SaveAvailabilityRuleRequest
{
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly DayOfWeek $dayOfWeek,
        public readonly string $openTime,
        public readonly string $closeTime,
        public readonly ?string $validFrom = null,
        public readonly ?string $validUntil = null,
    ) {
    }
}
