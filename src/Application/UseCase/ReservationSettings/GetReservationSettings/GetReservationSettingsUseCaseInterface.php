<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationSettings\GetReservationSettings;

interface GetReservationSettingsUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(GetReservationSettingsRequest $request): GetReservationSettingsResponse;
}
