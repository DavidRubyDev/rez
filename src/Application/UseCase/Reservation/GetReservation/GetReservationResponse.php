<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\GetReservation;

use Rez\Domain\Reservation\Reservation;

final class GetReservationResponse
{
    public function __construct(
        public readonly Reservation $reservation,
    ) {
    }
}
