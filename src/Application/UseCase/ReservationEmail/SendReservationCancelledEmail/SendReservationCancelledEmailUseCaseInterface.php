<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationCancelledEmail;

interface SendReservationCancelledEmailUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(SendReservationCancelledEmailRequest $request): SendReservationCancelledEmailResponse;
}
