<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationConfirmedEmail;

use Rez\Domain\Reservation\ReservationId;

final class SendReservationConfirmedEmailRequest
{
    public function __construct(
        public readonly ReservationId $reservationId,
    ) {
    }
}
