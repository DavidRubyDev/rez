<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailabilityOverrides;

use Rez\Domain\Availability\AvailabilityOverride;

final class GetAvailabilityOverridesResponse
{
    /**
     * @param AvailabilityOverride[] $overrides
     */
    public function __construct(
        public readonly array $overrides,
    ) {
    }
}
