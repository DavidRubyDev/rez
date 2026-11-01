<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\MarkReservationPaid;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;

final class MarkReservationPaidUseCase implements MarkReservationPaidUseCaseInterface
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
    public function execute(MarkReservationPaidRequest $request): MarkReservationPaidResponse
    {
        try {
            $reservation = $this->reservationRepository->findById($request->reservationId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation.', 0, $e);
        }

        $paid = $reservation->markPaid($request->amount, $request->method);

        try {
            $this->reservationRepository->save($paid);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save reservation.', 0, $e);
        }

        return new MarkReservationPaidResponse($paid);
    }
}
