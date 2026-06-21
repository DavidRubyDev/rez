<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityOverride;

interface SaveAvailabilityOverrideUseCaseInterface
{
    public function execute(SaveAvailabilityOverrideRequest $request): SaveAvailabilityOverrideResponse;
}
