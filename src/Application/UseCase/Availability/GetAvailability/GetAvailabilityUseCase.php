<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailability;

use Rez\Application\Service\AvailabilityServiceInterface;

final class GetAvailabilityUseCase implements GetAvailabilityUseCaseInterface
{
    public function __construct(
        private readonly AvailabilityServiceInterface $availabilityService,
    ) {
    }

    public function execute(GetAvailabilityRequest $request): GetAvailabilityResponse
    {
        $window = $this->availabilityService->getAvailableSlots(
            $request->resourceId,
            $request->date,
            $request->slotDurationMinutes,
        );

        return new GetAvailabilityResponse($window);
    }
}
