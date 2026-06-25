<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\DeleteAvailabilityOverride;

interface DeleteAvailabilityOverrideUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(DeleteAvailabilityOverrideRequest $request): DeleteAvailabilityOverrideResponse;
}
