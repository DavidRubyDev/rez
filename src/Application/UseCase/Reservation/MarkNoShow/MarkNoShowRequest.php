<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\MarkNoShow;

use Rez\Domain\Reservation\ReservationId;

final class MarkNoShowRequest
{
    public function __construct(
        public readonly ReservationId $reservationId,
    ) {
    }
}
