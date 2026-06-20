<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ListReservations;

interface ListReservationsUseCaseInterface
{
    public function execute(ListReservationsRequest $request): ListReservationsResponse;
}
