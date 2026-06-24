<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\ConfirmReservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\Reservation\ConfirmReservation\ConfirmReservationRequest;
use Rez\Application\UseCase\Reservation\ConfirmReservation\ConfirmReservationUseCase;
use Rez\Domain\Exception\DomainException;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class ConfirmReservationUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private ConfirmReservationUseCase $useCase;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->useCase               = new ConfirmReservationUseCase($this->reservationRepository);

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );
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
}
