<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CreateReservation;

use DateTimeImmutable;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Resource\ResourceId;

final class CreateReservationRequest
{
    /** @param ResourceId[] $resourceIds */
    public function __construct(
        public readonly array $resourceIds,
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
        public readonly Party $party,
        public readonly ?string $sessionId = null,
    ) {
    }
}
