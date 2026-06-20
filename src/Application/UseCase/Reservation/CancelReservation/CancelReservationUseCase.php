<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CancelReservation;

use Rez\Application\Port\ReservationRepositoryInterface;

final class CancelReservationUseCase implements CancelReservationUseCaseInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {
    }

    public function execute(CancelReservationRequest $request): CancelReservationResponse
    {
        $reservation = $this->reservationRepository->findById($request->reservationId);
        $cancelled   = $reservation->cancel();

        $this->reservationRepository->save($cancelled);

        return new CancelReservationResponse($cancelled);
    }
}
