<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailabilityOverrides;

interface GetAvailabilityOverridesUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(GetAvailabilityOverridesRequest $request): GetAvailabilityOverridesResponse;
}
