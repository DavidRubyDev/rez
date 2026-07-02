<?php

declare(strict_types=1);

namespace Rez\Application\Service;

use Psr\Log\LoggerInterface;
use Rez\Application\Port\MailerInterface;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationSettings;
use Rez\Domain\Shared\CancellationToken;

/**
 * Settings-gated auto-send for reservation lifecycle emails. Email failures never abort a
 * booking — every send is caught and logged, never re-thrown (see REZ-CONTEXT.md invariant 11).
 * This is the one place that invariant lives for all three lifecycle emails.
 *
 * ReservationSettings is passed in by the caller rather than loaded internally, so a use case
 * that needs it for other decisions (e.g. autoConfirm) only reads it once per request.
 */
final class ReservationEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendCreatedIfEnabled(
        Reservation $reservation,
        CancellationToken $cancellationToken,
        ReservationSettings $settings,
    ): void {
        if (!$settings->autoSendReservationCreated) {
            return;
        }

        $this->trySend(fn () => $this->mailer->sendReservationCreatedEmail($reservation, $cancellationToken));
    }

    public function sendConfirmedIfEnabled(
        Reservation $reservation,
        CancellationToken $cancellationToken,
        ReservationSettings $settings,
    ): void {
        if (!$settings->autoSendReservationConfirmed) {
            return;
        }

        $this->trySend(fn () => $this->mailer->sendReservationConfirmedEmail($reservation, $cancellationToken));
    }

    public function sendCancelledIfEnabled(
        Reservation $reservation,
        ReservationSettings $settings,
    ): void {
        if (!$settings->autoSendReservationCancelled) {
            return;
        }

        $this->trySend(fn () => $this->mailer->sendReservationCancelledEmail($reservation));
    }

    private function trySend(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            $this->logger->error('Reservation email failed to send', ['exception' => $e]);
        }
    }
}
