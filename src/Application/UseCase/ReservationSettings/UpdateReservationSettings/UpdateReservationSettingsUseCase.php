<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationSettings\UpdateReservationSettings;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;
use Rez\Domain\Reservation\ReservationSettings;

final class UpdateReservationSettingsUseCase implements UpdateReservationSettingsUseCaseInterface
{
    public function __construct(
        private readonly ReservationSettingsRepositoryInterface $reservationSettingsRepository,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(UpdateReservationSettingsRequest $request): UpdateReservationSettingsResponse
    {
        try {
            $existing = $this->reservationSettingsRepository->get();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation settings.', 0, $e);
        }

        $updated = new ReservationSettings(
            $request->autoConfirm ?? $existing->autoConfirm,
            $request->autoSendReservationCreated ?? $existing->autoSendReservationCreated,
            $request->autoSendReservationConfirmed ?? $existing->autoSendReservationConfirmed,
            $request->autoSendReservationCancelled ?? $existing->autoSendReservationCancelled,
        );

        try {
            $this->reservationSettingsRepository->update($updated);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save reservation settings.', 0, $e);
        }

        return new UpdateReservationSettingsResponse($updated);
    }
}
