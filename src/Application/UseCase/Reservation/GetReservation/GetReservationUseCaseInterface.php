<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\GetReservation;

interface GetReservationUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(GetReservationRequest $request): GetReservationResponse;
}
