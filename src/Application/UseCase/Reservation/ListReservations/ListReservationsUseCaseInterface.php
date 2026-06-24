<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ListReservations;

interface ListReservationsUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(ListReservationsRequest $request): ListReservationsResponse;
}
