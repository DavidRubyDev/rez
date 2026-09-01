<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Shared\CancellationToken;
use Rez\Domain\Shared\UnsubscribeToken;

interface MailerInterface
{
    public function sendReservationCreatedEmail(
        Reservation $reservation,
        CancellationToken $cancellationToken,
    ): void;

    public function sendReservationConfirmedEmail(
        Reservation $reservation,
        CancellationToken $cancellationToken,
    ): void;

    public function sendReservationCancelledEmail(
        Reservation $reservation,
    ): void;

    public function sendPasswordReset(
        string $email,
        string $resetUrl,
    ): void;

    public function sendNewClassNotification(
        string $email,
        string $className,
        \DateTimeImmutable $classDate,
        UnsubscribeToken $unsubscribeToken,
    ): void;

    /**
     * $unsubscribeToken is null when $recipientEmail is not a current newsletter subscriber —
     * an admin-composed custom email can go to any recipient list, not only subscribers, and an
     * unsubscribe link only makes sense for someone who is actually subscribed.
     */
    public function sendCustomEmail(
        string $recipientEmail,
        string $subject,
        string $htmlBody,
        ?UnsubscribeToken $unsubscribeToken,
    ): void;

    public function sendSubscriptionConfirmedEmail(
        string $email,
        ?string $name,
        UnsubscribeToken $unsubscribeToken,
    ): void;
}
