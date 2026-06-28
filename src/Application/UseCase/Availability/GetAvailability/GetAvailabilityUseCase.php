<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailability;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Service\AvailabilityServiceInterface;

final class GetAvailabilityUseCase implements GetAvailabilityUseCaseInterface
{
    public function __construct(
        private readonly AvailabilityServiceInterface $availabilityService,
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws DatabaseException
     */
    public function execute(GetAvailabilityRequest $request): GetAvailabilityResponse
    {
        try {
            $this->resourceRepository->findById($request->resourceId);
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
