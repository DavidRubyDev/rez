<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailabilityRules;

interface GetAvailabilityRulesUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(GetAvailabilityRulesRequest $request): GetAvailabilityRulesResponse;
}
