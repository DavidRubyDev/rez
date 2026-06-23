<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\Reservation\Reservation;

interface MailerInterface
{
    public function sendBookingConfirmation(
        string $recipientEmail,
        string $recipientName,
        Reservation $reservation,
    ): void;

    public function sendBookingCancellation(
        string $recipientEmail,
        string $recipientName,
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
    ): void;
}
