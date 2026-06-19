<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CancelReservation;

use Rez\Domain\Reservation\Reservation;

final class CancelReservationResponse
{
    public function __construct(
        public readonly Reservation $reservation,
    ) {
    }
}
