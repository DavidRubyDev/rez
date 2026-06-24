<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\GetReservation;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;

final class GetReservationUseCase implements GetReservationUseCaseInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     */
    public function execute(GetReservationRequest $request): GetReservationResponse
    {
        try {
            $reservation = $this->reservationRepository->findById($request->reservationId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation.', 0, $e);
        }

        return new GetReservationResponse($reservation);
    }
}
