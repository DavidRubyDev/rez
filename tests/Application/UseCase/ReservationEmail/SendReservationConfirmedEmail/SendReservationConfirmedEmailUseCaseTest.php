<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\ReservationEmail\SendReservationConfirmedEmail;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\ReservationEmail\SendReservationConfirmedEmail\SendReservationConfirmedEmailRequest;
use Rez\Application\UseCase\ReservationEmail\SendReservationConfirmedEmail\SendReservationConfirmedEmailUseCase;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Shared\CancellationToken;

class SendReservationConfirmedEmailUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private MailerInterface&MockObject $mailer;
    private SendReservationConfirmedEmailUseCase $useCase;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->mailer                = $this->createMock(MailerInterface::class);
        $usersConfig                = new UsersConfig('super-secret-jwt', 'super-secret-cancellation-key');
        $this->useCase                = new SendReservationConfirmedEmailUseCase(
            $this->reservationRepository,
            $this->mailer,
            $usersConfig,
        );

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );
    }

    public function testSendsConfirmedEmailDirectly(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->mailer->expects($this->once())
            ->method('sendReservationConfirmedEmail')
            ->with($this->reservation, $this->isInstanceOf(CancellationToken::class));

        $response = $this->useCase->execute(new SendReservationConfirmedEmailRequest($this->reservation->id));

        $this->assertSame($this->reservation, $response->reservation);
    }

    public function testMissingReservationThrowsReservationNotFoundException(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new ReservationNotFoundException());

        $this->expectException(ReservationNotFoundException::class);

        $this->useCase->execute(new SendReservationConfirmedEmailRequest(ReservationId::generate()));
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservation.');

        $this->useCase->execute(new SendReservationConfirmedEmailRequest(ReservationId::generate()));
    }

    public function testMailerExceptionPropagatesUnswallowed(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);
        $this->mailer->method('sendReservationConfirmedEmail')->willThrowException(new \RuntimeException('SMTP error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMTP error');

        $this->useCase->execute(new SendReservationConfirmedEmailRequest($this->reservation->id));
    }
}
