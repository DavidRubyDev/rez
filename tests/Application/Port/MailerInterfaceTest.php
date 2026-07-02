<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Port;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\MailerInterface;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Shared\CancellationToken;

class MailerInterfaceTest extends TestCase
{
    private Reservation $reservation;
    private CancellationToken $token;

    protected function setUp(): void
    {
        $id = ReservationId::generate();

        $this->reservation = Reservation::create(
            $id,
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            new Party('John Doe', 'john@example.com', 2, null),
        );
        $this->token = CancellationToken::generate($id, 'secret');
    }

    public function testSendReservationCreatedEmailAcceptsReservationAndCancellationToken(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('sendReservationCreatedEmail')
            ->with($this->reservation, $this->token);

        $mailer->sendReservationCreatedEmail($this->reservation, $this->token);
    }

    public function testSendReservationConfirmedEmailAcceptsReservationAndCancellationToken(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('sendReservationConfirmedEmail')
            ->with($this->reservation, $this->token);

        $mailer->sendReservationConfirmedEmail($this->reservation, $this->token);
    }

    public function testSendReservationCancelledEmailAcceptsReservationOnly(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('sendReservationCancelledEmail')
            ->with($this->reservation);

        $mailer->sendReservationCancelledEmail($this->reservation);
    }
}
