<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\MarkNoShow;

interface MarkNoShowUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws \Rez\Domain\Exception\InvalidReservationStateException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(MarkNoShowRequest $request): MarkNoShowResponse;
}
