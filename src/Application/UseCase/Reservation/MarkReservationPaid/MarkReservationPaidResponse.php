<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\MarkReservationPaid;

use Rez\Domain\Reservation\Reservation;

final class MarkReservationPaidResponse
{
    public function __construct(
        public readonly Reservation $reservation,
    ) {
    }
}
