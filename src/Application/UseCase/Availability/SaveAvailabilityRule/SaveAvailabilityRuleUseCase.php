<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityRule;

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
        $this->resourceRepository->findById($request->resourceId);

        $rule = new AvailabilityRule(
            $request->resourceId,
            $request->dayOfWeek,
            $request->openTime,
            $request->closeTime,
        );

        $this->availabilityRepository->saveRule($rule);

        return new SaveAvailabilityRuleResponse($rule);
    }
}
