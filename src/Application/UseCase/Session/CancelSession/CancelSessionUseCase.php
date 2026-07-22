<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\CancelSession;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\SessionRepositoryInterface;
use Rez\Application\UseCase\Reservation\BulkCancelReservations\BulkCancelReservationsRequest;
use Rez\Application\UseCase\Reservation\BulkCancelReservations\BulkCancelReservationsUseCaseInterface;
use Rez\Domain\Exception\InvalidSessionStateException;

final class CancelSessionUseCase implements CancelSessionUseCaseInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepository,
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly BulkCancelReservationsUseCaseInterface $bulkCancelReservationsUseCase,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\SessionNotFoundException
     * @throws InvalidSessionStateException
     * @throws DatabaseException
     */
    public function execute(CancelSessionRequest $request): CancelSessionResponse
    {
        try {
            $session = $this->sessionRepository->findById($request->sessionId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load session.', 0, $e);
        }

        $cancelled = $session->cancel();

        try {
            $this->sessionRepository->save($cancelled);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save session.', 0, $e);
        }

        try {
            $reservations = $this->reservationRepository->findBySessionId($request->sessionId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservations.', 0, $e);
        }

        $reservationIds = array_map(fn ($reservation) => $reservation->id, $reservations->toArray());

        $cancelledReservations = $this->bulkCancelReservationsUseCase->execute(
            new BulkCancelReservationsRequest($reservationIds),
        );

        return new CancelSessionResponse($cancelled, $cancelledReservations);
    }
}
