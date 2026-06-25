<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\BulkCancelReservations;

use Rez\Domain\Reservation\ReservationId;

final class BulkCancelReservationsRequest
{
    /** @param ReservationId[] $ids */
    public function __construct(
        public readonly array $ids,
    ) {
    }
}
