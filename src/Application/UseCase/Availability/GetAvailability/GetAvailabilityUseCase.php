<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailability;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Service\AvailabilityServiceInterface;

final class GetAvailabilityUseCase implements GetAvailabilityUseCaseInterface
{
    public function __construct(
        private readonly AvailabilityServiceInterface $availabilityService,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(GetAvailabilityRequest $request): GetAvailabilityResponse
    {
        try {
            $window = $this->availabilityService->getAvailableSlots(
                $request->resourceId,
                $request->date,
                $request->slotDurationMinutes,
            );
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to get availability.', 0, $e);
        }

        return new GetAvailabilityResponse($window);
    }
}
