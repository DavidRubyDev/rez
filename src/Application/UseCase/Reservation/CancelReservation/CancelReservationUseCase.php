<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Reservation\CancelReservation;

use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;
use Rez\Application\Service\ReservationEmailService;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Shared\CancellationToken;

final class CancelReservationUseCase implements CancelReservationUseCaseInterface
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
     * @throws InvalidTokenException
     * @throws DatabaseException
     */
    public function execute(CancelReservationRequest $request): CancelReservationResponse
    {
        try {
            $reservation = $this->reservationRepository->findById($request->reservationId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation.', 0, $e);
        }

        if ($request->cancellationToken !== null) {
            $this->assertValidToken($reservation, $request->cancellationToken);
        }

        return $this->cancel($reservation);
    }

    /**
     * @throws InvalidTokenException
     */
    private function assertValidToken(Reservation $reservation, string $cancellationToken): void
    {
        $token = CancellationToken::fromString($cancellationToken);

        if (!$token->verify($reservation->id, $this->usersConfig->cancellationSecret)) {
            throw new InvalidTokenException();
        }
    }

    /**
     * @throws \Rez\Domain\Exception\InvalidReservationStateException
     * @throws DatabaseException
     */
    private function cancel(Reservation $reservation): CancelReservationResponse
    {
        $cancelled = $reservation->cancel();

        try {
            $this->reservationRepository->save($cancelled);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save reservation.', 0, $e);
        }

        try {
            $settings = $this->reservationSettingsRepository->get();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation settings.', 0, $e);
        }

        $this->emailService->sendCancelledIfEnabled($cancelled, $settings);

        return new CancelReservationResponse($cancelled);
    }
}
