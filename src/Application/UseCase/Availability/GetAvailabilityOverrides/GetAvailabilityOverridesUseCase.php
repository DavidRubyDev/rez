<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailabilityOverrides;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;

final class GetAvailabilityOverridesUseCase implements GetAvailabilityOverridesUseCaseInterface
{
    public function __construct(
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(GetAvailabilityOverridesRequest $request): GetAvailabilityOverridesResponse
    {
        try {
            $overrides = $this->availabilityRepository->findOverridesForResource(
                $request->resourceId,
                $request->from,
                $request->to,
            );
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to get availability overrides.', 0, $e);
        }

        return new GetAvailabilityOverridesResponse($overrides);
    }
}
