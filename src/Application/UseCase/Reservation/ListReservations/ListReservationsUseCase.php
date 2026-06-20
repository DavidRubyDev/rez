<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ListReservations;

use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Domain\Reservation\Reservation;

final class ListReservationsUseCase implements ListReservationsUseCaseInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {
    }

    public function execute(ListReservationsRequest $request): ListReservationsResponse
    {
        $reservations = $this->reservationRepository->findAll($request->from, $request->to);

        if ($request->resourceId !== null) {
            $resourceId   = $request->resourceId;
            $reservations = $reservations->filter(
                fn (Reservation $r) => $r->resourceIds->contains($resourceId)
            );
        }

        return new ListReservationsResponse($reservations);
    }
}
