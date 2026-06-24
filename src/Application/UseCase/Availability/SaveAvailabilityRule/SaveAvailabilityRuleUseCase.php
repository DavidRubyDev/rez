<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityRule;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Domain\Availability\AvailabilityRule;

final class SaveAvailabilityRuleUseCase implements SaveAvailabilityRuleUseCaseInterface
{
    public function __construct(
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     */
    public function execute(SaveAvailabilityRuleRequest $request): SaveAvailabilityRuleResponse
    {
        try {
            $this->resourceRepository->findById($request->resourceId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load resource.', 0, $e);
        }

        $rule = new AvailabilityRule(
            $request->resourceId,
            $request->dayOfWeek,
            $request->openTime,
            $request->closeTime,
        );

        try {
            $this->availabilityRepository->saveRule($rule);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save availability rule.', 0, $e);
        }

        return new SaveAvailabilityRuleResponse($rule);
    }
}
