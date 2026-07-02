<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationConfirmedEmail;

interface SendReservationConfirmedEmailUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(SendReservationConfirmedEmailRequest $request): SendReservationConfirmedEmailResponse;
}
