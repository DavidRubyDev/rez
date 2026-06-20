<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailability;

interface GetAvailabilityUseCaseInterface
{
    public function execute(GetAvailabilityRequest $request): GetAvailabilityResponse;
}
