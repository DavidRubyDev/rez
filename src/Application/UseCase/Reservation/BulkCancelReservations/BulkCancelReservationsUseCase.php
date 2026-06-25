<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\BulkCancelReservations;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;

final class BulkCancelReservationsUseCase implements BulkCancelReservationsUseCaseInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {
    }

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(BulkCancelReservationsRequest $request): BulkCancelReservationsResponse
    {
        $cancelled = [];
        $skipped   = [];

        foreach ($request->ids as $id) {
            try {
                $reservation = $this->reservationRepository->findById($id);
            } catch (ReservationNotFoundException) {
                $skipped[] = $id;
                continue;
            } catch (DatabaseException $e) {
                throw new DatabaseException('Failed to load reservation.', 0, $e);
            }

            if ($reservation->status === ReservationStatus::Cancelled) {
                $cancelled[] = $reservation;
                continue;
            }

            $cancelledReservation = $reservation->cancel();

            try {
                $this->reservationRepository->save($cancelledReservation);
            } catch (DatabaseException $e) {
                throw new DatabaseException('Failed to save reservation.', 0, $e);
            }

            $cancelled[] = $cancelledReservation;
        }

        return new BulkCancelReservationsResponse($cancelled, $skipped);
    }
}
