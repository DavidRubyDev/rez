<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityRule;

use Rez\Domain\Availability\AvailabilityRule;

final class SaveAvailabilityRuleResponse
{
    public function __construct(
        public readonly AvailabilityRule $rule,
    ) {
    }
}
