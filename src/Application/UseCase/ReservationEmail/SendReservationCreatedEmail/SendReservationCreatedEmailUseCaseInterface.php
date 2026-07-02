<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationCreatedEmail;

interface SendReservationCreatedEmailUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(SendReservationCreatedEmailRequest $request): SendReservationCreatedEmailResponse;
}
