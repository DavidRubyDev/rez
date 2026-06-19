<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CreateReservation;

use Rez\Domain\Reservation\Reservation;

final class CreateReservationResponse
{
    public function __construct(
        public readonly Reservation $reservation,
    ) {
    }
}
