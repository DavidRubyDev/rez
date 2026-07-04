<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CancelReservation;

interface CancelReservationUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws \Rez\Domain\Exception\InvalidReservationStateException
     * @throws \Rez\Domain\Exception\InvalidTokenException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(CancelReservationRequest $request): CancelReservationResponse;
}
