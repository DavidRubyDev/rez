<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CreateReservation;

interface CreateReservationUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \Rez\Domain\Exception\InvalidTimeSlotException
     * @throws \Rez\Domain\Exception\ConflictException
     */
    public function execute(CreateReservationRequest $request): CreateReservationResponse;
}
