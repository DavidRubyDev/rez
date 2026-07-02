<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationConfirmedEmail;

use Rez\Domain\Reservation\Reservation;

final class SendReservationConfirmedEmailResponse
{
    public function __construct(
        public readonly Reservation $reservation,
    ) {
    }
}
