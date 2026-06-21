<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityOverride;

use Rez\Domain\Availability\AvailabilityOverride;

final class SaveAvailabilityOverrideResponse
{
    public function __construct(
        public readonly AvailabilityOverride $override,
    ) {
    }
}
