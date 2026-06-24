<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityOverride;

interface SaveAvailabilityOverrideUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(SaveAvailabilityOverrideRequest $request): SaveAvailabilityOverrideResponse;
}
