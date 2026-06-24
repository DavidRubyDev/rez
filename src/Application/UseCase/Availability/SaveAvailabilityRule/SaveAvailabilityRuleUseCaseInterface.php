<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityRule;

interface SaveAvailabilityRuleUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     */
    public function execute(SaveAvailabilityRuleRequest $request): SaveAvailabilityRuleResponse;
}
