<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\GetReservation;

interface GetReservationUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     */
    public function execute(GetReservationRequest $request): GetReservationResponse;
}
