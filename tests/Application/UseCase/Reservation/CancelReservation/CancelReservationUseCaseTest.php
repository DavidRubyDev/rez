<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\CancelReservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\Reservation\CancelReservation\CancelReservationRequest;
use Rez\Application\UseCase\Reservation\CancelReservation\CancelReservationUseCase;
use Rez\Domain\Exception\DomainException;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class CancelReservationUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private CancelReservationUseCase $useCase;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->useCase               = new CancelReservationUseCase($this->reservationRepository);

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

        $this->useCase->execute(new CancelReservationRequest(ReservationId::generate()));
    }

    public function testAlreadyCancelledThrowsDomainException(): void
    {
        $cancelled = $this->reservation->cancel();

        $this->reservationRepository->method('findById')->willReturn($cancelled);

        $this->expectException(DomainException::class);

        $this->useCase->execute(new CancelReservationRequest($cancelled->id()));
    }

    public function testSuccessSaveCalledWithCancelledReservation(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->reservationRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (Reservation $r) => $r->status() === ReservationStatus::Cancelled
            ));

        $this->useCase->execute(new CancelReservationRequest($this->reservation->id()));
    }

    public function testSuccessResponseHasCancelledStatus(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $response = $this->useCase->execute(new CancelReservationRequest($this->reservation->id()));

        $this->assertSame(ReservationStatus::Cancelled, $response->reservation->status());
    }
}
