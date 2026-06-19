<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CancelReservation;

use Rez\Domain\Reservation\ReservationId;

final class CancelReservationRequest
{
    public function __construct(
        public readonly ReservationId $reservationId,
    ) {
    }
}
