<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationCancelledEmail;

use Rez\Domain\Reservation\Reservation;

final class SendReservationCancelledEmailResponse
{
    public function __construct(
        public readonly Reservation $reservation,
    ) {
    }
}
