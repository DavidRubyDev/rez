<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\ConfirmReservation;

use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;
use Rez\Application\Service\ReservationEmailService;
use Rez\Domain\Shared\CancellationToken;

final class ConfirmReservationUseCase implements ConfirmReservationUseCaseInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly ReservationSettingsRepositoryInterface $reservationSettingsRepository,
        private readonly ReservationEmailService $emailService,
        private readonly UsersConfig $usersConfig,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws \Rez\Domain\Exception\InvalidReservationStateException
     * @throws DatabaseException
     */
    public function execute(ConfirmReservationRequest $request): ConfirmReservationResponse
    {
        try {
            $reservation = $this->reservationRepository->findById($request->reservationId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation.', 0, $e);
        }

        $confirmed = $reservation->confirm();

        try {
            $this->reservationRepository->save($confirmed);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save reservation.', 0, $e);
        }

        try {
            $settings = $this->reservationSettingsRepository->get();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation settings.', 0, $e);
        }

        $token = CancellationToken::generate($confirmed->id, $this->usersConfig->cancellationSecret);
        $this->emailService->sendConfirmedIfEnabled($confirmed, $token, $settings);

        return new ConfirmReservationResponse($confirmed);
    }
}
