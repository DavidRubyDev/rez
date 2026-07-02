<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\CancelReservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;
use Rez\Application\Service\ReservationEmailService;
use Rez\Application\UseCase\Reservation\CancelReservation\CancelReservationRequest;
use Rez\Application\UseCase\Reservation\CancelReservation\CancelReservationUseCase;
use Rez\Domain\Exception\DomainException;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationSettings;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class CancelReservationUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private ReservationSettingsRepositoryInterface&MockObject $reservationSettingsRepository;
    private MailerInterface&MockObject $mailer;
    private ReservationEmailService $emailService;
    private CancelReservationUseCase $useCase;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->reservationRepository         = $this->createMock(ReservationRepositoryInterface::class);
        $this->reservationSettingsRepository = $this->createMock(ReservationSettingsRepositoryInterface::class);
        $this->reservationSettingsRepository
            ->method('get')
            ->willReturn(new ReservationSettings(true, true, true, true));
        $this->mailer       = $this->createMock(MailerInterface::class);
        $this->emailService = new ReservationEmailService($this->mailer, new NullLogger());
        $this->useCase      = $this->makeUseCase($this->reservationSettingsRepository);

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );
    }

    private function makeUseCase(
        ReservationSettingsRepositoryInterface $reservationSettingsRepository,
    ): CancelReservationUseCase {
        return new CancelReservationUseCase(
            $this->reservationRepository,
            $reservationSettingsRepository,
            $this->emailService,
        );
    }

    private function settingsRepositoryReturning(ReservationSettings $settings): ReservationSettingsRepositoryInterface&MockObject
    {
        $repository = $this->createMock(ReservationSettingsRepositoryInterface::class);
        $repository->method('get')->willReturn($settings);

        return $repository;
    }

    public function testNotFoundThrowsReservationNotFoundException(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new ReservationNotFoundException());

        $this->expectException(ReservationNotFoundException::class);

        $this->useCase->execute(new CancelReservationRequest(ReservationId::generate()));
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservation.');

        $this->useCase->execute(new CancelReservationRequest(ReservationId::generate()));
    }

    public function testReservationSettingsDatabaseExceptionPropagates(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $reservationSettingsRepository = $this->createMock(ReservationSettingsRepositoryInterface::class);
        $reservationSettingsRepository
            ->method('get')
            ->willThrowException(new DatabaseException('pdo error'));

        $useCase = $this->makeUseCase($reservationSettingsRepository);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservation settings.');

        $useCase->execute(new CancelReservationRequest($this->reservation->id));
    }

    public function testAlreadyCancelledThrowsDomainException(): void
    {
        $cancelled = $this->reservation->cancel();

        $this->reservationRepository->method('findById')->willReturn($cancelled);

        $this->expectException(DomainException::class);

        $this->useCase->execute(new CancelReservationRequest($cancelled->id));
    }

    public function testSuccessSaveCalledWithCancelledReservation(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->reservationRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (Reservation $r) => $r->status === ReservationStatus::Cancelled
            ));

        $this->useCase->execute(new CancelReservationRequest($this->reservation->id));
    }

    public function testSuccessResponseHasCancelledStatus(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $response = $this->useCase->execute(new CancelReservationRequest($this->reservation->id));

        $this->assertSame(ReservationStatus::Cancelled, $response->reservation->status);
    }

    public function testSendsCancelledEmailWhenEnabled(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->mailer->expects($this->once())
            ->method('sendReservationCancelledEmail')
            ->with($this->isInstanceOf(Reservation::class));

        $this->useCase->execute(new CancelReservationRequest($this->reservation->id));
    }

    public function testDoesNotSendCancelledEmailWhenDisabled(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->mailer->expects($this->never())->method('sendReservationCancelledEmail');

        $useCase = $this->makeUseCase($this->settingsRepositoryReturning(
            new ReservationSettings(true, true, true, false),
        ));
        $useCase->execute(new CancelReservationRequest($this->reservation->id));
    }

    public function testEmailFailureDoesNotAbortCancellation(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);
        $this->mailer->method('sendReservationCancelledEmail')->willThrowException(new \RuntimeException('SMTP error'));

        $response = $this->useCase->execute(new CancelReservationRequest($this->reservation->id));

        $this->assertSame(ReservationStatus::Cancelled, $response->reservation->status);
    }
}
