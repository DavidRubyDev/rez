<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\DeleteAvailabilityRule;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;

final class DeleteAvailabilityRuleUseCase implements DeleteAvailabilityRuleUseCaseInterface
{
    public function __construct(
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
    ) {
    }

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(DeleteAvailabilityRuleRequest $request): DeleteAvailabilityRuleResponse
    {
        try {
            $this->availabilityRepository->deleteRule($request->resourceId, $request->dayOfWeek);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to delete availability rule.', 0, $e);
        }

        return new DeleteAvailabilityRuleResponse();
    }
}
