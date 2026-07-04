<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationCreatedEmail;

use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Domain\Shared\CancellationToken;

final class SendReservationCreatedEmailUseCase implements SendReservationCreatedEmailUseCaseInterface
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
    public function execute(SendReservationCreatedEmailRequest $request): SendReservationCreatedEmailResponse
    {
        try {
            $reservation = $this->reservationRepository->findById($request->reservationId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation.', 0, $e);
        }

        $token = CancellationToken::generate($reservation->id, $this->usersConfig->cancellationSecret);

        // Deliberately not caught: this is a manual, explicit admin action — unlike the
        // settings-gated auto-send path (ReservationEmailService), a mailer failure here must
        // surface so rez-starter's error middleware can map it and rez-admin can show it failed.
        $this->mailer->sendReservationCreatedEmail($reservation, $token);

        return new SendReservationCreatedEmailResponse($reservation);
    }
}
