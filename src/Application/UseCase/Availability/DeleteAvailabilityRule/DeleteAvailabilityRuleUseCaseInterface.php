<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\DeleteAvailabilityRule;

interface DeleteAvailabilityRuleUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(DeleteAvailabilityRuleRequest $request): DeleteAvailabilityRuleResponse;
}
