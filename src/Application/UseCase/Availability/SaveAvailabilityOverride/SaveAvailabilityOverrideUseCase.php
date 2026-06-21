<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityOverride;

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

    public function execute(SaveAvailabilityOverrideRequest $request): SaveAvailabilityOverrideResponse
    {
        $this->resourceRepository->findById($request->resourceId);

        $override = new AvailabilityOverride(
            $request->resourceId,
            $request->date,
            $request->available,
        );

        $this->availabilityRepository->saveOverride($override);

        return new SaveAvailabilityOverrideResponse($override);
    }
}
