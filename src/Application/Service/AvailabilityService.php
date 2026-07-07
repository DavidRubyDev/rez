<?php

declare(strict_types=1);

namespace Rez\Application\Service;

use DateInterval;
use DateTimeImmutable;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Availability\AvailabilityWindow;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;

final class AvailabilityService implements AvailabilityServiceInterface
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resourceRepository,
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {
    }

    public function isSlotAvailable(ResourceId $resourceId, TimeSlot $slot, int $partySize = 1): bool
    {
        $rules = $this->availabilityRepository->findRulesForResource($resourceId);
        $rule  = $this->findRuleForDate($rules, $slot->start);

        if ($rule === null) {
            return false;
        }

        $dayStart  = $slot->start->setTime(0, 0);
        $dayEnd    = $dayStart->modify('+1 day');
        $overrides = $this->availabilityRepository->findOverridesForResource($resourceId, $dayStart, $dayEnd);

        if ($this->isBlockedByOverride($overrides, $slot->start)) {
            return false;
        }

        $resource = $this->resourceRepository->findById($resourceId);

        if (!$resource->active) {
            return false;
        }

        $reservations = $this->reservationRepository->findByTimeSlotAndResource($slot, $resourceId);
        $occupied     = $this->sumOverlappingPartySize($slot, $reservations);

        return $occupied + $partySize <= $resource->capacity;
    }

    public function getAvailableSlots(
        ResourceId $resourceId,
        DateTimeImmutable $date,
        int $slotDurationMinutes,
        int $partySize = 1,
    ): AvailabilityWindow {
        $rules = $this->availabilityRepository->findRulesForResource($resourceId);
        $rule  = $this->findRuleForDate($rules, $date);

        if ($rule === null) {
            return AvailabilityWindow::empty($resourceId, $date);
        }

        $dayEnd    = $date->modify('+1 day');
        $overrides = $this->availabilityRepository->findOverridesForResource($resourceId, $date, $dayEnd);

        if ($this->isBlockedByOverride($overrides, $date)) {
            return AvailabilityWindow::empty($resourceId, $date);
        }

        $resource = $this->resourceRepository->findById($resourceId);

        if (!$resource->active) {
            return AvailabilityWindow::empty($resourceId, $date);
        }

        $candidates   = $this->generateCandidateSlots($rule, $date, $slotDurationMinutes);
        $fullDaySlot  = new TimeSlot($rule->openTimeForDate($date), $rule->closeTimeForDate($date));
        $reservations = $this->reservationRepository->findByTimeSlotAndResource($fullDaySlot, $resourceId);
        $available    = $this->filterAvailableSlots($candidates, $reservations, $resource->capacity, $partySize);

        return new AvailabilityWindow($resourceId, $date, $available);
    }

    /** @param AvailabilityRule[] $rules */
    private function findRuleForDate(array $rules, DateTimeImmutable $date): ?AvailabilityRule
    {
        foreach ($rules as $rule) {
            if ($rule->appliesToDate($date) && $rule->isActiveOn($date)) {
                return $rule;
            }
        }

        return null;
    }

    /** @param AvailabilityOverride[] $overrides */
    private function isBlockedByOverride(array $overrides, DateTimeImmutable $date): bool
    {
        $dateString = $date->format('Y-m-d');

        foreach ($overrides as $override) {
            if ($override->date->format('Y-m-d') === $dateString && !$override->isAvailable) {
                return true;
            }
        }

        return false;
    }

    /** @return list<TimeSlot> */
    private function generateCandidateSlots(AvailabilityRule $rule, DateTimeImmutable $date, int $slotDurationMinutes): array
    {
        $slots    = [];
        $interval = new DateInterval("PT{$slotDurationMinutes}M");
        $close    = $rule->closeTimeForDate($date);
        $start    = $rule->openTimeForDate($date);

        while (true) {
            $end = $start->add($interval);

            if ($end > $close) {
                break;
            }

            $slots[] = new TimeSlot($start, $end);
            $start   = $end;
        }

        return $slots;
    }

    /**
     * @param list<TimeSlot> $candidates
     * @return list<TimeSlot>
     */
    private function filterAvailableSlots(
        array $candidates,
        ReservationCollection $reservations,
        int $capacity,
        int $partySize,
    ): array {
        return array_values(array_filter(
            $candidates,
            function (TimeSlot $candidate) use ($reservations, $capacity, $partySize): bool {
                $occupied = $this->sumOverlappingPartySize($candidate, $reservations);
                return $occupied + $partySize <= $capacity;
            }
        ));
    }

    private function sumOverlappingPartySize(TimeSlot $slot, ReservationCollection $reservations): int
    {
        $total = 0;
        foreach ($reservations->toArray() as $reservation) {
            if ($reservation->slot->overlapsWith($slot)) {
                $total += $reservation->party->size;
            }
        }
        return $total;
    }
}
