<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CancelReservation;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;

final class CancelReservationUseCase implements CancelReservationUseCaseInterface
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
    public function execute(CancelReservationRequest $request): CancelReservationResponse
    {
        try {
            $reservation = $this->reservationRepository->findById($request->reservationId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation.', 0, $e);
        }

        $cancelled = $reservation->cancel();

        try {
            $this->reservationRepository->save($cancelled);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save reservation.', 0, $e);
        }

        return new CancelReservationResponse($cancelled);
    }
}
