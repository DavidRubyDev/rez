<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CreateReservation;

use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Exception\ConflictException;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceIdCollection;

final class CreateReservationUseCase
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly ResourceRepositoryInterface $resourceRepository,
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
    ) {
    }

    public function execute(CreateReservationRequest $request): CreateReservationResponse
    {
        $resources = [];

        foreach ($request->resourceIds as $resourceId) {
            $resources[] = $this->resourceRepository->findById($resourceId);
        }

        $slot = new TimeSlot($request->start, $request->end);

        foreach ($resources as $resource) {
            $this->assertAvailable($slot, $resource);
        }

        $reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray($request->resourceIds),
            $slot,
            $request->party,
        );

        $this->reservationRepository->save($reservation);

        return new CreateReservationResponse($reservation);
    }

    private function assertAvailable(TimeSlot $slot, Resource $resource): void
    {
        $rules = $this->availabilityRepository->findRulesForResource($resource->id());

        $rule = $this->findRuleForDate($rules, $slot);

        if ($rule === null) {
            throw new ConflictException($slot, $resource);
        }

        $dayStart  = $slot->start()->setTime(0, 0);
        $dayEnd    = $dayStart->modify('+1 day');
        $overrides = $this->availabilityRepository->findOverridesForResource($resource->id(), $dayStart, $dayEnd);

        if ($this->isBlockedByOverride($overrides, $slot)) {
            throw new ConflictException($slot, $resource);
        }

        $conflicts = $this->reservationRepository->findByTimeSlotAndResource($slot, $resource->id());

        if (!$conflicts->isEmpty()) {
            throw new ConflictException($slot, $resource);
        }
    }

    /** @param AvailabilityRule[] $rules */
    private function findRuleForDate(array $rules, TimeSlot $slot): ?AvailabilityRule
    {
        foreach ($rules as $rule) {
            if ($rule->appliesToDate($slot->start())) {
                return $rule;
            }
        }

        return null;
    }

    /** @param AvailabilityOverride[] $overrides */
    private function isBlockedByOverride(array $overrides, TimeSlot $slot): bool
    {
        $date = $slot->start()->format('Y-m-d');

        foreach ($overrides as $override) {
            if ($override->date()->format('Y-m-d') === $date && !$override->isAvailable()) {
                return true;
            }
        }

        return false;
    }
}
