<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\GetReservation;

use Rez\Domain\Reservation\ReservationId;

final class GetReservationRequest
{
    public function __construct(
        public readonly ReservationId $reservationId,
    ) {
    }
}
