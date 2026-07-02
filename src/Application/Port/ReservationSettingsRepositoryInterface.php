<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\Reservation\ReservationSettings;

interface ReservationSettingsRepositoryInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function get(): ReservationSettings;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function update(ReservationSettings $settings): void;
}
