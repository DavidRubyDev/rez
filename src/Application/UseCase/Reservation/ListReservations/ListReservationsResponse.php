<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ListReservations;

use Rez\Domain\Reservation\ReservationCollection;

final class ListReservationsResponse
{
    public function __construct(
        public readonly ReservationCollection $reservations,
        public readonly int $total,
    ) {
    }
}
