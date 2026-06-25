<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\DeleteAvailabilityOverride;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;

final class DeleteAvailabilityOverrideUseCase implements DeleteAvailabilityOverrideUseCaseInterface
{
    public function __construct(
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
    ) {
    }

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(DeleteAvailabilityOverrideRequest $request): DeleteAvailabilityOverrideResponse
    {
        try {
            $this->availabilityRepository->deleteOverride($request->resourceId, $request->date);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to delete availability override.', 0, $e);
        }

        return new DeleteAvailabilityOverrideResponse();
    }
}
