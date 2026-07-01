<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\SaveAvailabilityRule;

use DateTimeImmutable;
use DateTimeZone;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Shared\Utc;

final class SaveAvailabilityRuleUseCase implements SaveAvailabilityRuleUseCaseInterface
{
    public function __construct(
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
        private readonly ResourceRepositoryInterface $resourceRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \InvalidArgumentException
     * @throws DatabaseException
     */
    public function execute(SaveAvailabilityRuleRequest $request): SaveAvailabilityRuleResponse
    {
        try {
            $this->resourceRepository->findById($request->resourceId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load resource.', 0, $e);
        }

        $utc       = Utc::timezone();
        $validFrom = $this->parseDate($request->validFrom, 'validFrom', $utc);
        $validUntil = $this->parseDate($request->validUntil, 'validUntil', $utc);

        if ($validFrom !== null && $validUntil !== null && $validFrom > $validUntil) {
            throw new \InvalidArgumentException('validFrom must not be after validUntil.');
        }

        $rule = new AvailabilityRule(
            $request->resourceId,
            $request->dayOfWeek,
            $request->openTime,
            $request->closeTime,
            validFrom:  $validFrom,
            validUntil: $validUntil,
        );

        try {
            $this->availabilityRepository->saveRule($rule);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save availability rule.', 0, $e);
        }

        return new SaveAvailabilityRuleResponse($rule);
    }

    /** @throws \InvalidArgumentException */
    private function parseDate(?string $value, string $field, DateTimeZone $utc): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value, $utc);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException("Invalid date format for {$field}: expected Y-m-d.");
        }

        return $date->setTime(0, 0);
    }
}
