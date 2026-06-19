<?php

declare(strict_types=1);

namespace Rez\Application\Service;

use DateTimeImmutable;
use Rez\Domain\Availability\AvailabilityWindow;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;

interface AvailabilityServiceInterface
{
    public function isSlotAvailable(ResourceId $resourceId, TimeSlot $slot): bool;

    public function getAvailableSlots(ResourceId $resourceId, DateTimeImmutable $date, int $slotDurationMinutes): AvailabilityWindow;
}
