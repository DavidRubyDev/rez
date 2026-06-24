<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityRule;

interface SaveAvailabilityRuleUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(SaveAvailabilityRuleRequest $request): SaveAvailabilityRuleResponse;
}
