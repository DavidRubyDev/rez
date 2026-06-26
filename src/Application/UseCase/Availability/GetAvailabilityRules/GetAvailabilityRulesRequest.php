<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailabilityRules;

use Rez\Domain\Resource\ResourceId;

final class GetAvailabilityRulesRequest
{
    public function __construct(
        public readonly ResourceId $resourceId,
    ) {
    }
}
