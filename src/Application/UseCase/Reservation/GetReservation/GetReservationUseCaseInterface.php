<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\GetReservation;

interface GetReservationUseCaseInterface
{
    public function execute(GetReservationRequest $request): GetReservationResponse;
}
