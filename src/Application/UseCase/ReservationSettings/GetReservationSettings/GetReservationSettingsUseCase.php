<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationSettings\GetReservationSettings;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;

final class GetReservationSettingsUseCase implements GetReservationSettingsUseCaseInterface
{
    public function __construct(
        private readonly ReservationSettingsRepositoryInterface $reservationSettingsRepository,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(GetReservationSettingsRequest $request): GetReservationSettingsResponse
    {
        try {
            $settings = $this->reservationSettingsRepository->get();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation settings.', 0, $e);
        }

        return new GetReservationSettingsResponse($settings);
    }
}
