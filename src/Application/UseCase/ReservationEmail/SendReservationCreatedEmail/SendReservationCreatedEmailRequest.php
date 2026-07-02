<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationCreatedEmail;

use Rez\Domain\Reservation\ReservationId;

final class SendReservationCreatedEmailRequest
{
    public function __construct(
        public readonly ReservationId $reservationId,
    ) {
    }
}
