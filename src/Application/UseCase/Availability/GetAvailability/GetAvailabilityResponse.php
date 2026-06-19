<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailability;

use Rez\Domain\Availability\AvailabilityWindow;

final class GetAvailabilityResponse
{
    public function __construct(
        public readonly AvailabilityWindow $window,
    ) {
    }
}
