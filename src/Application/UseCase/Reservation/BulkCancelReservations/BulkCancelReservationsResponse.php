<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\BulkCancelReservations;

use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;

final class BulkCancelReservationsResponse
{
    /**
     * @param Reservation[]   $cancelled
     * @param ReservationId[] $skipped
     */
    public function __construct(
        public readonly array $cancelled,
        public readonly array $skipped,
    ) {
    }
}
