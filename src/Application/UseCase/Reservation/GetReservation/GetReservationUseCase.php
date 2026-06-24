<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\GetReservation;

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
        $reservation = $this->reservationRepository->findById($request->reservationId);

        return new GetReservationResponse($reservation);
    }
}
