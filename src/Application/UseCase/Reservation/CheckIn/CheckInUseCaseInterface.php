<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CheckIn;

interface CheckInUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws \Rez\Domain\Exception\InvalidReservationStateException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(CheckInRequest $request): CheckInResponse;
}
