<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\MarkNoShow;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\Reservation\MarkNoShow\MarkNoShowRequest;
use Rez\Application\UseCase\Reservation\MarkNoShow\MarkNoShowUseCase;
use Rez\Domain\Exception\DomainException;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class MarkNoShowUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private MarkNoShowUseCase $useCase;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->useCase               = new MarkNoShowUseCase($this->reservationRepository);

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        )->confirm();
    }

    public function testNotFoundThrowsReservationNotFoundException(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new ReservationNotFoundException());

        $this->expectException(ReservationNotFoundException::class);

        $this->useCase->execute(new MarkNoShowRequest(ReservationId::generate()));
    }

    public function testNotConfirmedThrowsDomainException(): void
    {
        $pending = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );

        $this->reservationRepository->method('findById')->willReturn($pending);

        $this->expectException(DomainException::class);

        $this->useCase->execute(new MarkNoShowRequest($pending->id));
    }

    public function testSuccessSaveCalledWithNoShowReservation(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->reservationRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (Reservation $r) => $r->status === ReservationStatus::NoShow
            ));

        $this->useCase->execute(new MarkNoShowRequest($this->reservation->id));
    }

    public function testSuccessResponseHasNoShowStatus(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $response = $this->useCase->execute(new MarkNoShowRequest($this->reservation->id));

        $this->assertSame(ReservationStatus::NoShow, $response->reservation->status);
    }
}
