<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ConfirmReservation;

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
     */
    public function execute(ConfirmReservationRequest $request): ConfirmReservationResponse
    {
        $reservation = $this->reservationRepository->findById($request->reservationId);
        $confirmed   = $reservation->confirm();

        $this->reservationRepository->save($confirmed);

        return new ConfirmReservationResponse($confirmed);
    }
}
