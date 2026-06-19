<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailability;

use DateInterval;
use Rez\Application\Port\AvailabilityRepositoryInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Domain\Availability\AvailabilityOverride;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Availability\AvailabilityWindow;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\TimeSlot;

final class GetAvailabilityUseCase
{
    public function __construct(
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {
    }

    public function execute(GetAvailabilityRequest $request): GetAvailabilityResponse
    {
        $rules = $this->availabilityRepository->findRulesForResource($request->resourceId);

        $rule = $this->findRuleForDate($rules, $request);

        if ($rule === null) {
            return new GetAvailabilityResponse(AvailabilityWindow::empty($request->resourceId, $request->date));
        }

        $dayStart = $request->date;
        $dayEnd   = $request->date->modify('+1 day');

        $overrides = $this->availabilityRepository->findOverridesForResource($request->resourceId, $dayStart, $dayEnd);

        if ($this->isBlockedByOverride($overrides, $request)) {
            return new GetAvailabilityResponse(AvailabilityWindow::empty($request->resourceId, $request->date));
        }

        $candidates = $this->generateCandidateSlots($rule, $request);

        $fullDaySlot  = new TimeSlot($rule->openTimeForDate($request->date), $rule->closeTimeForDate($request->date));
        $reservations = $this->reservationRepository->findByTimeSlotAndResource($fullDaySlot, $request->resourceId);

        $available = $this->filterConflictingSlots($candidates, $reservations);

        return new GetAvailabilityResponse(new AvailabilityWindow($request->resourceId, $request->date, $available));
    }

    /** @param AvailabilityRule[] $rules */
    private function findRuleForDate(array $rules, GetAvailabilityRequest $request): ?AvailabilityRule
    {
        foreach ($rules as $rule) {
            if ($rule->appliesToDate($request->date)) {
                return $rule;
            }
        }

        return null;
    }

    /** @param AvailabilityOverride[] $overrides */
    private function isBlockedByOverride(array $overrides, GetAvailabilityRequest $request): bool
    {
        foreach ($overrides as $override) {
            if ($override->date()->format('Y-m-d') === $request->date->format('Y-m-d') && !$override->isAvailable()) {
                return true;
            }
        }

        return false;
    }

    /** @return TimeSlot[] */
    private function generateCandidateSlots(AvailabilityRule $rule, GetAvailabilityRequest $request): array
    {
        $slots    = [];
        $interval = new DateInterval("PT{$request->slotDurationMinutes}M");
        $close    = $rule->closeTimeForDate($request->date);
        $start    = $rule->openTimeForDate($request->date);

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
     * @param TimeSlot[] $candidates
     * @return TimeSlot[]
     */
    private function filterConflictingSlots(array $candidates, ReservationCollection $reservations): array
    {
        return array_values(array_filter(
            $candidates,
            function (TimeSlot $candidate) use ($reservations): bool {
                foreach ($reservations->toArray() as $reservation) {
                    if ($candidate->overlapsWith($reservation->slot())) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }
}
