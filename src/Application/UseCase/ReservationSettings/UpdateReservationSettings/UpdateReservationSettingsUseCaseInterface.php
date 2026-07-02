<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationSettings\UpdateReservationSettings;

interface UpdateReservationSettingsUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(UpdateReservationSettingsRequest $request): UpdateReservationSettingsResponse;
}
