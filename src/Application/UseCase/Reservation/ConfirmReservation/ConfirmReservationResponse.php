<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ConfirmReservation;

use Rez\Domain\Reservation\Reservation;

final class ConfirmReservationResponse
{
    public function __construct(
        public readonly Reservation $reservation,
    ) {
    }
}
