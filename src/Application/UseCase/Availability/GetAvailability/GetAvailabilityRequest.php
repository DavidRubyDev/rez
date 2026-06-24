<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Availability\GetAvailability;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class GetAvailabilityRequest
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly DateTimeImmutable $date,
        public readonly int $slotDurationMinutes,
    ) {
        if ($slotDurationMinutes <= 0) {
            throw new \InvalidArgumentException('Slot duration must be greater than zero.');
        }
    }
}
