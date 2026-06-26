<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailabilityRules;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;

final class GetAvailabilityRulesUseCase implements GetAvailabilityRulesUseCaseInterface
{
    public function __construct(
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(GetAvailabilityRulesRequest $request): GetAvailabilityRulesResponse
    {
        try {
            $rules = $this->availabilityRepository->findRulesForResource($request->resourceId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to get availability rules.', 0, $e);
        }

        return new GetAvailabilityRulesResponse($rules);
    }
}
