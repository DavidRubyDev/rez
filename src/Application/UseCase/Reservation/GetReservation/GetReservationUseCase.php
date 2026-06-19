<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\GetReservation;

use Rez\Application\Port\ReservationRepositoryInterface;

final class GetReservationUseCase
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {
    }

    public function execute(GetReservationRequest $request): GetReservationResponse
    {
        $reservation = $this->reservationRepository->findById($request->reservationId);

        return new GetReservationResponse($reservation);
    }
}
