<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ConfirmReservation;

interface ConfirmReservationUseCaseInterface
{
    public function execute(ConfirmReservationRequest $request): ConfirmReservationResponse;
}
