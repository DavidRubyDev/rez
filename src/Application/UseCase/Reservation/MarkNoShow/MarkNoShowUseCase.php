<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\MarkNoShow;

use Rez\Application\Port\ReservationRepositoryInterface;

final class MarkNoShowUseCase implements MarkNoShowUseCaseInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {
    }

    public function execute(MarkNoShowRequest $request): MarkNoShowResponse
    {
        $reservation = $this->reservationRepository->findById($request->reservationId);
        $noShow      = $reservation->markNoShow();

        $this->reservationRepository->save($noShow);

        return new MarkNoShowResponse($noShow);
    }
}
