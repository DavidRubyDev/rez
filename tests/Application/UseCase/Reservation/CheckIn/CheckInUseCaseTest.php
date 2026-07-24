<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\CheckIn;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\Reservation\CheckIn\CheckInRequest;
use Rez\Application\UseCase\Reservation\CheckIn\CheckInUseCase;
use Rez\Domain\Exception\DomainException;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class CheckInUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private CheckInUseCase $useCase;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->useCase                = new CheckInUseCase($this->reservationRepository);

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        )->confirm();
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservation.');

        $this->useCase->execute(new CheckInRequest(ReservationId::generate()));
    }

    public function testNotFoundThrowsReservationNotFoundException(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new ReservationNotFoundException());

        $this->expectException(ReservationNotFoundException::class);

        $this->useCase->execute(new CheckInRequest(ReservationId::generate()));
    }

    public function testPendingThrowsDomainException(): void
    {
        $pending = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );

        $this->reservationRepository->method('findById')->willReturn($pending);

        $this->expectException(DomainException::class);

        $this->useCase->execute(new CheckInRequest($pending->id));
    }

    public function testSuccessSaveCalledWithCheckedInReservation(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->reservationRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (Reservation $r) => $r->status === ReservationStatus::CheckedIn && $r->checkedIn !== null
            ));

        $this->useCase->execute(new CheckInRequest($this->reservation->id));
    }

    public function testSuccessResponseHasCheckedInStatus(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $response = $this->useCase->execute(new CheckInRequest($this->reservation->id));

        $this->assertSame(ReservationStatus::CheckedIn, $response->reservation->status);
        $this->assertNotNull($response->reservation->checkedIn);
    }

    public function testSuccessFromNoShowReturnsCheckedInStatus(): void
    {
        $noShow = $this->reservation->markNoShow();
        $this->reservationRepository->method('findById')->willReturn($noShow);

        $response = $this->useCase->execute(new CheckInRequest($noShow->id));

        $this->assertSame(ReservationStatus::CheckedIn, $response->reservation->status);
    }
}
