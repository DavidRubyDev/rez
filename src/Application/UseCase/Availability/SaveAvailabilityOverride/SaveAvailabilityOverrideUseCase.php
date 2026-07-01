<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityOverride;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Domain\Availability\AvailabilityOverride;

final class SaveAvailabilityOverrideUseCase implements SaveAvailabilityOverrideUseCaseInterface
{
    public function __construct(
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws DatabaseException
     */
    public function execute(SaveAvailabilityOverrideRequest $request): SaveAvailabilityOverrideResponse
    {
        try {
            $this->resourceRepository->findById($request->resourceId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load resource.', 0, $e);
        }

        $override = new AvailabilityOverride(
            $request->resourceId,
            $request->date,
            $request->available,
        );

        try {
            $this->availabilityRepository->saveOverride($override);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save availability override.', 0, $e);
        }

        return new SaveAvailabilityOverrideResponse($override);
    }
}
