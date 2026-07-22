<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\CreateReservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\ReservationSettingsRepositoryInterface;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Application\Port\SessionRepositoryInterface;
use Rez\Application\Service\AvailabilityServiceInterface;
use Rez\Application\Service\ReservationEmailService;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationRequest;
use Rez\Application\UseCase\Reservation\CreateReservation\CreateReservationUseCase;
use Rez\Domain\Exception\ConflictException;
use Rez\Domain\Exception\InvalidSessionStateException;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Exception\SessionNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationSettings;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Resource\ResourceType;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionId;
use Rez\Domain\Shared\CancellationToken;

class CreateReservationUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private ResourceRepositoryInterface&MockObject $resourceRepository;
    private AvailabilityServiceInterface&MockObject $availabilityService;
    private ReservationSettingsRepositoryInterface&MockObject $reservationSettingsRepository;
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private MailerInterface&MockObject $mailer;
    private ReservationEmailService $emailService;
    private UsersConfig $usersConfig;
    private CreateReservationUseCase $useCase;
    private ResourceId $resourceId;
    private Resource $resource;
    private Party $party;

    protected function setUp(): void
    {
        $this->reservationRepository        = $this->createMock(ReservationRepositoryInterface::class);
        $this->resourceRepository           = $this->createMock(ResourceRepositoryInterface::class);
        $this->availabilityService          = $this->createMock(AvailabilityServiceInterface::class);
        $this->reservationSettingsRepository = $this->createMock(ReservationSettingsRepositoryInterface::class);
        $this->reservationSettingsRepository
            ->method('get')
            ->willReturn(new ReservationSettings(false, true, true, true));
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->mailer       = $this->createMock(MailerInterface::class);
        $this->emailService = new ReservationEmailService($this->mailer, new NullLogger());
        $this->usersConfig = new UsersConfig('super-secret-jwt', 'super-secret-cancellation-key');
        $this->useCase      = $this->makeUseCase($this->reservationSettingsRepository);

        $this->resourceId = ResourceId::generate();
        $this->resource   = new Resource($this->resourceId, ResourceType::fromString('table'), 'Table 1', 4);
        $this->party      = new Party('John Doe', 'john@example.com', 2, null);
    }

    private function makeUseCase(
        ReservationSettingsRepositoryInterface $reservationSettingsRepository,
    ): CreateReservationUseCase {
        return new CreateReservationUseCase(
            $this->reservationRepository,
            $this->resourceRepository,
            $this->availabilityService,
            $reservationSettingsRepository,
            $this->emailService,
            $this->usersConfig,
            $this->sessionRepository,
        );
    }

    private function settingsRepositoryReturning(ReservationSettings $settings): ReservationSettingsRepositoryInterface&MockObject
    {
        $repository = $this->createMock(ReservationSettingsRepositoryInterface::class);
        $repository->method('get')->willReturn($settings);

        return $repository;
    }

    private function request(): CreateReservationRequest
    {
        return new CreateReservationRequest(
            [$this->resourceId],
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
            $this->party,
        );
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->resourceRepository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load resource.');

        $this->useCase->execute($this->request());
    }

    public function testReservationSettingsDatabaseExceptionPropagates(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $reservationSettingsRepository = $this->createMock(ReservationSettingsRepositoryInterface::class);
        $reservationSettingsRepository
            ->method('get')
            ->willThrowException(new DatabaseException('pdo error'));

        $useCase = $this->makeUseCase($reservationSettingsRepository);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservation settings.');

        $useCase->execute($this->request());
    }

    public function testResourceNotFoundThrowsResourceNotFoundException(): void
    {
        $this->resourceRepository
            ->method('findById')
            ->willThrowException(new ResourceNotFoundException());

        $this->expectException(ResourceNotFoundException::class);

        $this->useCase->execute($this->request());
    }

    public function testInvalidTimeSlotThrowsInvalidTimeSlotException(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $this->expectException(\Rez\Domain\Exception\InvalidTimeSlotException::class);

        $this->useCase->execute(new CreateReservationRequest(
            [$this->resourceId],
            new DateTimeImmutable('2024-01-15 11:00:00'),
            new DateTimeImmutable('2024-01-15 10:00:00'),
            $this->party,
        ));
    }

    public function testUnavailableSlotThrowsConflictException(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(false);

        $this->expectException(ConflictException::class);

        $this->useCase->execute($this->request());
    }

    public function testSuccessSaveCalledOnce(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $this->reservationRepository->expects($this->once())->method('save');

        $this->useCase->execute($this->request());
    }

    public function testAutoConfirmSaveCalledOnce(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $this->reservationRepository->expects($this->once())->method('save');

        $useCase = $this->makeUseCase($this->settingsRepositoryReturning(new ReservationSettings(true, true, true, true)));

        $useCase->execute($this->request());
    }

    public function testSuccessReturnsPendingReservation(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $response = $this->useCase->execute($this->request());

        $this->assertSame(ReservationStatus::Pending, $response->reservation->status);
    }

    public function testSuccessReservationHasCorrectResourceId(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $response = $this->useCase->execute($this->request());

        $this->assertTrue($response->reservation->resourceIds->contains($this->resourceId));
    }

    public function testAutoConfirmFalseLeavesPending(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $useCase = $this->makeUseCase($this->settingsRepositoryReturning(new ReservationSettings(false, true, true, true)));

        $response = $useCase->execute($this->request());

        $this->assertSame(ReservationStatus::Pending, $response->reservation->status);
    }

    public function testAutoConfirmTrueConfirmsImmediately(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $useCase = $this->makeUseCase($this->settingsRepositoryReturning(new ReservationSettings(true, true, true, true)));

        $response = $useCase->execute($this->request());

        $this->assertSame(ReservationStatus::Confirmed, $response->reservation->status);
    }

    public function testConflictWhenPartySizeExceedsCapacity(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(false);

        $this->expectException(ConflictException::class);

        $this->useCase->execute(new CreateReservationRequest(
            [$this->resourceId],
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
            new Party('John Doe', 'john@example.com', 5, null),
        ));
    }

    public function testPartySizeIsPassedToAvailabilityService(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $this->availabilityService
            ->expects($this->once())
            ->method('isSlotAvailable')
            ->with(
                $this->resourceId,
                $this->isInstanceOf(TimeSlot::class),
                2,
            )
            ->willReturn(true);

        $this->useCase->execute($this->request());
    }

    // --- autoConfirm × auto-send-flag email matrix ---
    // Hard requirement: suppressing the created email when autoConfirm is true must be
    // structural (single if/else in the use case), not an accidental consequence of flags.

    public function testAutoConfirmFalseAndCreatedEnabledSendsOnlyCreatedEmail(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $this->mailer->expects($this->once())
            ->method('sendReservationCreatedEmail')
            ->with($this->isInstanceOf(Reservation::class), $this->isInstanceOf(CancellationToken::class));
        $this->mailer->expects($this->never())->method('sendReservationConfirmedEmail');

        $useCase = $this->makeUseCase($this->settingsRepositoryReturning(new ReservationSettings(false, true, true, true)));

        $useCase->execute($this->request());
    }

    public function testAutoConfirmFalseAndCreatedDisabledSendsNoEmail(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $this->mailer->expects($this->never())->method('sendReservationCreatedEmail');
        $this->mailer->expects($this->never())->method('sendReservationConfirmedEmail');

        $useCase = $this->makeUseCase($this->settingsRepositoryReturning(new ReservationSettings(false, false, true, true)));

        $useCase->execute($this->request());
    }

    public function testAutoConfirmTrueAndConfirmedEnabledSendsOnlyConfirmedEmailEvenWhenCreatedAlsoEnabled(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $this->mailer->expects($this->never())->method('sendReservationCreatedEmail');
        $this->mailer->expects($this->once())
            ->method('sendReservationConfirmedEmail')
            ->with($this->isInstanceOf(Reservation::class), $this->isInstanceOf(CancellationToken::class));

        // autoSendReservationCreated is true here too — confirmed-only must win structurally.
        $useCase = $this->makeUseCase($this->settingsRepositoryReturning(new ReservationSettings(true, true, true, true)));

        $useCase->execute($this->request());
    }

    public function testAutoConfirmTrueAndConfirmedDisabledSendsNoEmail(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $this->mailer->expects($this->never())->method('sendReservationCreatedEmail');
        $this->mailer->expects($this->never())->method('sendReservationConfirmedEmail');

        $useCase = $this->makeUseCase($this->settingsRepositoryReturning(new ReservationSettings(true, true, false, true)));

        $useCase->execute($this->request());
    }

    public function testGeneratedTokenVerifiesForSavedReservation(): void
    {
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        $capturedToken = null;
        $this->mailer->method('sendReservationCreatedEmail')
            ->willReturnCallback(function (Reservation $reservation, CancellationToken $token) use (&$capturedToken) {
                $capturedToken = $token;
            });

        $response = $this->useCase->execute($this->request());

        $this->assertInstanceOf(CancellationToken::class, $capturedToken);
        $this->assertTrue($capturedToken->verify($response->reservation->id, $this->usersConfig->cancellationSecret));
    }

    // --- session path ---

    private function makeSession(int $capacity = 4): Session
    {
        return Session::create(
            SessionId::generate(),
            $this->resourceId,
            new DateTimeImmutable('2024-06-03 09:00:00'),
            60,
            $capacity,
        );
    }

    private function sessionRequest(SessionId $sessionId, ?ResourceId $bodyResourceId = null): CreateReservationRequest
    {
        return new CreateReservationRequest(
            [$bodyResourceId ?? ResourceId::generate()],
            new DateTimeImmutable('1999-01-01 00:00:00'),
            new DateTimeImmutable('1999-01-01 01:00:00'),
            $this->party,
            $sessionId->toString(),
        );
    }

    public function testSessionPathIgnoresBodySuppliedResourceIdAndTimeSlot(): void
    {
        $session = $this->makeSession();
        $this->sessionRepository->method('findById')->willReturn($session);
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->reservationRepository->method('findBySessionId')->willReturn(ReservationCollection::empty());

        $bodyResourceId = ResourceId::generate();

        $response = $this->useCase->execute($this->sessionRequest($session->id, $bodyResourceId));

        $this->assertTrue($response->reservation->resourceIds->contains($this->resourceId));
        $this->assertFalse($response->reservation->resourceIds->contains($bodyResourceId));
        $this->assertTrue($session->toTimeSlot()->equals($response->reservation->slot));
    }

    public function testSessionPathHappyCaseSavesReservationWithSessionId(): void
    {
        $session = $this->makeSession();
        $this->sessionRepository->method('findById')->willReturn($session);
        $this->resourceRepository->method('findById')->willReturn($this->resource);
        $this->reservationRepository->method('findBySessionId')->willReturn(ReservationCollection::empty());

        $this->reservationRepository->expects($this->once())->method('save');

        $response = $this->useCase->execute($this->sessionRequest($session->id));

        $this->assertNotNull($response->reservation->sessionId);
        $this->assertTrue($session->id->equals($response->reservation->sessionId));
    }

    public function testSessionNotFoundPropagates(): void
    {
        $this->sessionRepository->method('findById')->willThrowException(new SessionNotFoundException());

        $this->expectException(SessionNotFoundException::class);

        $this->useCase->execute($this->sessionRequest(SessionId::generate()));
    }

    public function testCancelledSessionThrowsInvalidSessionStateException(): void
    {
        $session = $this->makeSession()->cancel();
        $this->sessionRepository->method('findById')->willReturn($session);

        $this->expectException(InvalidSessionStateException::class);

        $this->useCase->execute($this->sessionRequest($session->id));
    }

    public function testSessionCapacityExceededThrowsConflictException(): void
    {
        $session = $this->makeSession(capacity: 2);
        $this->sessionRepository->method('findById')->willReturn($session);
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $existing = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId]),
            $session->toTimeSlot(),
            new Party('Existing Guest', 'existing@example.com', 1, null),
            $session->id,
        );
        $this->reservationRepository->method('findBySessionId')->willReturn(ReservationCollection::fromArray([$existing]));

        $this->expectException(ConflictException::class);

        // 1 existing + 2 incoming > capacity of 2
        $this->useCase->execute($this->sessionRequest($session->id));
    }

    public function testSessionCapacityIgnoresCancelledReservations(): void
    {
        $session = $this->makeSession(capacity: 2);
        $this->sessionRepository->method('findById')->willReturn($session);
        $this->resourceRepository->method('findById')->willReturn($this->resource);

        $cancelled = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId]),
            $session->toTimeSlot(),
            new Party('Existing Guest', 'existing@example.com', 2, null),
            $session->id,
        )->cancel();
        $this->reservationRepository->method('findBySessionId')->willReturn(ReservationCollection::fromArray([$cancelled]));

        $response = $this->useCase->execute($this->sessionRequest($session->id));

        $this->assertSame(ReservationStatus::Pending, $response->reservation->status);
    }
}
