<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CheckIn;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;

final class CheckInUseCase implements CheckInUseCaseInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws \Rez\Domain\Exception\InvalidReservationStateException
     * @throws DatabaseException
     */
    public function execute(CheckInRequest $request): CheckInResponse
    {
        try {
            $reservation = $this->reservationRepository->findById($request->reservationId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation.', 0, $e);
        }

        $checkedIn = $reservation->checkIn();

        try {
            $this->reservationRepository->save($checkedIn);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save reservation.', 0, $e);
        }

        return new CheckInResponse($checkedIn);
    }
}
