<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\ConfirmReservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;
use Rez\Application\Service\ReservationEmailService;
use Rez\Application\UseCase\Reservation\ConfirmReservation\ConfirmReservationRequest;
use Rez\Application\UseCase\Reservation\ConfirmReservation\ConfirmReservationUseCase;
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
use Rez\Domain\Shared\CancellationToken;

class ConfirmReservationUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private ReservationSettingsRepositoryInterface&MockObject $reservationSettingsRepository;
    private MailerInterface&MockObject $mailer;
    private ReservationEmailService $emailService;
    private UsersConfig $usersConfig;
    private ConfirmReservationUseCase $useCase;
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
        $this->usersConfig = new UsersConfig('super-secret-jwt', 'super-secret-cancellation-key');
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
    ): ConfirmReservationUseCase {
        return new ConfirmReservationUseCase(
            $this->reservationRepository,
            $reservationSettingsRepository,
            $this->emailService,
            $this->usersConfig,
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

        $this->useCase->execute(new ConfirmReservationRequest(ReservationId::generate()));
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservation.');

        $this->useCase->execute(new ConfirmReservationRequest(ReservationId::generate()));
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

        $useCase->execute(new ConfirmReservationRequest($this->reservation->id));
    }

    public function testAlreadyConfirmedThrowsDomainException(): void
    {
        $confirmed = $this->reservation->confirm();

        $this->reservationRepository->method('findById')->willReturn($confirmed);

        $this->expectException(DomainException::class);

        $this->useCase->execute(new ConfirmReservationRequest($confirmed->id));
    }

    public function testSuccessSaveCalledWithConfirmedReservation(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->reservationRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (Reservation $r) => $r->status === ReservationStatus::Confirmed
            ));

        $this->useCase->execute(new ConfirmReservationRequest($this->reservation->id));
    }

    public function testSuccessResponseHasConfirmedStatus(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $response = $this->useCase->execute(new ConfirmReservationRequest($this->reservation->id));

        $this->assertSame(ReservationStatus::Confirmed, $response->reservation->status);
    }

    public function testSendsConfirmedEmailWhenEnabled(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->mailer->expects($this->once())
            ->method('sendReservationConfirmedEmail')
            ->with($this->isInstanceOf(Reservation::class), $this->isInstanceOf(CancellationToken::class));

        $this->useCase->execute(new ConfirmReservationRequest($this->reservation->id));
    }

    public function testDoesNotSendConfirmedEmailWhenDisabled(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->mailer->expects($this->never())->method('sendReservationConfirmedEmail');

        $useCase = $this->makeUseCase($this->settingsRepositoryReturning(
            new ReservationSettings(true, true, false, true),
        ));
        $useCase->execute(new ConfirmReservationRequest($this->reservation->id));
    }

    public function testEmailFailureDoesNotAbortConfirmation(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);
        $this->mailer->method('sendReservationConfirmedEmail')->willThrowException(new \RuntimeException('SMTP error'));

        $response = $this->useCase->execute(new ConfirmReservationRequest($this->reservation->id));

        $this->assertSame(ReservationStatus::Confirmed, $response->reservation->status);
    }
}
