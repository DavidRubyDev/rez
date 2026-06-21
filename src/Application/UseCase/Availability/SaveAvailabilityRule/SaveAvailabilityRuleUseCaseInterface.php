<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityRule;

interface SaveAvailabilityRuleUseCaseInterface
{
    public function execute(SaveAvailabilityRuleRequest $request): SaveAvailabilityRuleResponse;
}
