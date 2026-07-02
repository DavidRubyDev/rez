<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\ReservationEmail\SendReservationCreatedEmail;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Config\MailerConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\ReservationEmail\SendReservationCreatedEmail\SendReservationCreatedEmailRequest;
use Rez\Application\UseCase\ReservationEmail\SendReservationCreatedEmail\SendReservationCreatedEmailUseCase;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Shared\CancellationToken;

class SendReservationCreatedEmailUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private MailerInterface&MockObject $mailer;
    private SendReservationCreatedEmailUseCase $useCase;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->mailer                = $this->createMock(MailerInterface::class);
        $mailerConfig                = new MailerConfig('super-secret-cancellation-key');
        $this->useCase                = new SendReservationCreatedEmailUseCase(
            $this->reservationRepository,
            $this->mailer,
            $mailerConfig,
        );

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );
    }

    public function testSendsCreatedEmailDirectly(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->mailer->expects($this->once())
            ->method('sendReservationCreatedEmail')
            ->with($this->reservation, $this->isInstanceOf(CancellationToken::class));

        $response = $this->useCase->execute(new SendReservationCreatedEmailRequest($this->reservation->id));

        $this->assertSame($this->reservation, $response->reservation);
    }

    public function testMissingReservationThrowsReservationNotFoundException(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new ReservationNotFoundException());

        $this->expectException(ReservationNotFoundException::class);

        $this->useCase->execute(new SendReservationCreatedEmailRequest(ReservationId::generate()));
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservation.');

        $this->useCase->execute(new SendReservationCreatedEmailRequest(ReservationId::generate()));
    }

    public function testMailerExceptionPropagatesUnswallowed(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);
        $this->mailer->method('sendReservationCreatedEmail')->willThrowException(new \RuntimeException('SMTP error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMTP error');

        $this->useCase->execute(new SendReservationCreatedEmailRequest($this->reservation->id));
    }
}
