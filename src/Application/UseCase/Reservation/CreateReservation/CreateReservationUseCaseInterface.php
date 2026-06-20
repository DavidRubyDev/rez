<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CreateReservation;

interface CreateReservationUseCaseInterface
{
    public function execute(CreateReservationRequest $request): CreateReservationResponse;
}
