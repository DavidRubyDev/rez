<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CheckIn;

use Rez\Domain\Reservation\Reservation;

final class CheckInResponse
{
    public function __construct(
        public readonly Reservation $reservation,
    ) {
    }
}
