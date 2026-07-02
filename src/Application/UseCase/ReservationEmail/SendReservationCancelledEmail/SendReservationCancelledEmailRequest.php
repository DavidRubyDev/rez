<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationCancelledEmail;

use Rez\Domain\Reservation\ReservationId;

final class SendReservationCancelledEmailRequest
{
    public function __construct(
        public readonly ReservationId $reservationId,
    ) {
    }
}
