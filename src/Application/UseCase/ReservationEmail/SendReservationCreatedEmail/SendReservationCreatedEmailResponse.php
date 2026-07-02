<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationCreatedEmail;

use Rez\Domain\Reservation\Reservation;

final class SendReservationCreatedEmailResponse
{
    public function __construct(
        public readonly Reservation $reservation,
    ) {
    }
}
