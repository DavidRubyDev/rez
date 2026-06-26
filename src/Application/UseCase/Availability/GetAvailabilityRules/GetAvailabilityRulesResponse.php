<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailabilityRules;

use Rez\Domain\Availability\AvailabilityRule;

final class GetAvailabilityRulesResponse
{
    /**
     * @param AvailabilityRule[] $rules
     */
    public function __construct(
        public readonly array $rules,
    ) {
    }
}
