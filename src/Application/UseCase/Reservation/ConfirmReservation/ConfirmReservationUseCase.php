<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ConfirmReservation;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;

final class ConfirmReservationUseCase implements ConfirmReservationUseCaseInterface
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
    public function execute(ConfirmReservationRequest $request): ConfirmReservationResponse
    {
        try {
            $reservation = $this->reservationRepository->findById($request->reservationId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation.', 0, $e);
        }

        $confirmed = $reservation->confirm();

        try {
            $this->reservationRepository->save($confirmed);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save reservation.', 0, $e);
        }

        return new ConfirmReservationResponse($confirmed);
    }
}
