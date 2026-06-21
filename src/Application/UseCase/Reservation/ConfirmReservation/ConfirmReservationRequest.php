<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ConfirmReservation;

use Rez\Domain\Reservation\ReservationId;

final class ConfirmReservationRequest
{
    public function __construct(
        public readonly ReservationId $reservationId,
    ) {
    }
}
