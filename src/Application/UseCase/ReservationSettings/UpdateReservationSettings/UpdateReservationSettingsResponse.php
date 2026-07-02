<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationSettings\UpdateReservationSettings;

use Rez\Domain\Reservation\ReservationSettings;

final class UpdateReservationSettingsResponse
{
    public function __construct(
        public readonly ReservationSettings $settings,
    ) {
    }
}
