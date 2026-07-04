<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationConfirmedEmail;

use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Domain\Shared\CancellationToken;

final class SendReservationConfirmedEmailUseCase implements SendReservationConfirmedEmailUseCaseInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly MailerInterface $mailer,
        private readonly UsersConfig $usersConfig,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws DatabaseException
     */
    public function execute(SendReservationConfirmedEmailRequest $request): SendReservationConfirmedEmailResponse
    {
        try {
            $reservation = $this->reservationRepository->findById($request->reservationId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation.', 0, $e);
        }

        $token = CancellationToken::generate($reservation->id, $this->usersConfig->cancellationSecret);

        // Deliberately not caught — see SendReservationCreatedEmailUseCase.
        $this->mailer->sendReservationConfirmedEmail($reservation, $token);

        return new SendReservationConfirmedEmailResponse($reservation);
    }
}
