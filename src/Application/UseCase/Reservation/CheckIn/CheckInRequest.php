<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CheckIn;

use Rez\Domain\Reservation\ReservationId;

final class CheckInRequest
{
    public function __construct(
        public readonly ReservationId $reservationId,
    ) {
    }
}
