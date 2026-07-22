<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Session\CancelSession;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\Port\SessionRepositoryInterface;
use Rez\Application\UseCase\Reservation\BulkCancelReservations\BulkCancelReservationsRequest;
use Rez\Application\UseCase\Reservation\BulkCancelReservations\BulkCancelReservationsResponse;
use Rez\Application\UseCase\Reservation\BulkCancelReservations\BulkCancelReservationsUseCaseInterface;
use Rez\Application\UseCase\Session\CancelSession\CancelSessionRequest;
use Rez\Application\UseCase\Session\CancelSession\CancelSessionUseCase;
use Rez\Domain\Exception\InvalidSessionStateException;
use Rez\Domain\Exception\SessionNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionId;
use Rez\Domain\Session\SessionStatus;

class CancelSessionUseCaseTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private BulkCancelReservationsUseCaseInterface&MockObject $bulkCancelReservationsUseCase;
    private CancelSessionUseCase $useCase;
    private SessionId $sessionId;
    private Session $session;

    protected function setUp(): void
    {
        $this->sessionRepository             = $this->createMock(SessionRepositoryInterface::class);
        $this->reservationRepository         = $this->createMock(ReservationRepositoryInterface::class);
        $this->bulkCancelReservationsUseCase = $this->createMock(BulkCancelReservationsUseCaseInterface::class);
        $this->useCase                       = new CancelSessionUseCase(
            $this->sessionRepository,
            $this->reservationRepository,
            $this->bulkCancelReservationsUseCase,
        );

        $this->sessionId = SessionId::generate();
        $this->session    = Session::create($this->sessionId, ResourceId::generate(), new DateTimeImmutable('2024-06-03 09:00:00'), 60, 10);
    }

    public function testSessionNotFoundPropagates(): void
    {
        $this->sessionRepository->method('findById')->willThrowException(new SessionNotFoundException());

        $this->expectException(SessionNotFoundException::class);

        $this->useCase->execute(new CancelSessionRequest($this->sessionId));
    }

    public function testFindByIdDatabaseExceptionPropagates(): void
    {
        $this->sessionRepository->method('findById')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load session.');

        $this->useCase->execute(new CancelSessionRequest($this->sessionId));
    }

    public function testAlreadyCancelledSessionThrowsInvalidSessionStateException(): void
    {
        $this->sessionRepository->method('findById')->willReturn($this->session->cancel());

        $this->expectException(InvalidSessionStateException::class);

        $this->useCase->execute(new CancelSessionRequest($this->sessionId));
    }

    public function testSaveDatabaseExceptionPropagates(): void
    {
        $this->sessionRepository->method('findById')->willReturn($this->session);
        $this->sessionRepository->method('save')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save session.');

        $this->useCase->execute(new CancelSessionRequest($this->sessionId));
    }

    public function testSuccessSavesCancelledSession(): void
    {
        $this->sessionRepository->method('findById')->willReturn($this->session);
        $this->reservationRepository->method('findBySessionId')->willReturn(ReservationCollection::empty());
        $this->bulkCancelReservationsUseCase->method('execute')->willReturn(new BulkCancelReservationsResponse([], []));

        $this->sessionRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (Session $s) => $s->status === SessionStatus::Cancelled));

        $response = $this->useCase->execute(new CancelSessionRequest($this->sessionId));

        $this->assertSame(SessionStatus::Cancelled, $response->session->status);
    }

    public function testFindBySessionIdDatabaseExceptionPropagates(): void
    {
        $this->sessionRepository->method('findById')->willReturn($this->session);
        $this->reservationRepository->method('findBySessionId')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservations.');

        $this->useCase->execute(new CancelSessionRequest($this->sessionId));
    }

    public function testBulkCancelsEveryReservationOnTheSession(): void
    {
        $this->sessionRepository->method('findById')->willReturn($this->session);

        $party        = new Party('John Doe', 'john@example.com', 2, null);
        $reservationA = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-06-03 09:00:00'), new DateTimeImmutable('2024-06-03 10:00:00')),
            $party,
            $this->sessionId,
        );
        $reservationB = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-06-03 09:00:00'), new DateTimeImmutable('2024-06-03 10:00:00')),
            $party,
            $this->sessionId,
        );

        $this->reservationRepository
            ->method('findBySessionId')
            ->with($this->sessionId)
            ->willReturn(ReservationCollection::fromArray([$reservationA, $reservationB]));

        $this->bulkCancelReservationsUseCase
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(
                fn (BulkCancelReservationsRequest $request) => count($request->ids) === 2
                    && $request->ids[0]->equals($reservationA->id)
                    && $request->ids[1]->equals($reservationB->id)
            ))
            ->willReturn(new BulkCancelReservationsResponse([$reservationA->cancel(), $reservationB->cancel()], []));

        $response = $this->useCase->execute(new CancelSessionRequest($this->sessionId));

        $this->assertSame(2, count($response->cancelledReservations->cancelled));
    }
}
