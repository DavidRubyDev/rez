<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\ReservationEmail\SendReservationCancelledEmail;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\ReservationRepositoryInterface;

final class SendReservationCancelledEmailUseCase implements SendReservationCancelledEmailUseCaseInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\ReservationNotFoundException
     * @throws DatabaseException
     */
    public function execute(SendReservationCancelledEmailRequest $request): SendReservationCancelledEmailResponse
    {
        try {
            $reservation = $this->reservationRepository->findById($request->reservationId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load reservation.', 0, $e);
        }

        // Deliberately not caught — see SendReservationCreatedEmailUseCase. No token needed:
        // sendReservationCancelledEmail() doesn't take one.
        $this->mailer->sendReservationCancelledEmail($reservation);

        return new SendReservationCancelledEmailResponse($reservation);
    }
}
