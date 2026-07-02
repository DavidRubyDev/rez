<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationSettings\GetReservationSettings;

use Rez\Domain\Reservation\ReservationSettings;

final class GetReservationSettingsResponse
{
    public function __construct(
        public readonly ReservationSettings $settings,
    ) {
    }
}
