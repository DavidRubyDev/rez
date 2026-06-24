<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ConfirmReservation;

interface ConfirmReservationUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws \Rez\Domain\Exception\InvalidReservationStateException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(ConfirmReservationRequest $request): ConfirmReservationResponse;
}
