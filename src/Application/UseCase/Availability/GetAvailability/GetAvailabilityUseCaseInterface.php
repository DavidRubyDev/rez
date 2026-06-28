<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailability;

interface GetAvailabilityUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(GetAvailabilityRequest $request): GetAvailabilityResponse;
}
