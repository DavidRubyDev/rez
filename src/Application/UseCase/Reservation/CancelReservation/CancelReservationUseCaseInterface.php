<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CancelReservation;

interface CancelReservationUseCaseInterface
{
    public function execute(CancelReservationRequest $request): CancelReservationResponse;
}
