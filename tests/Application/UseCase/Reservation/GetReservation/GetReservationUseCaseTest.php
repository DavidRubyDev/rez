<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\GetReservation;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\Reservation\GetReservation\GetReservationRequest;
use Rez\Application\UseCase\Reservation\GetReservation\GetReservationUseCase;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class GetReservationUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private GetReservationUseCase $useCase;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->useCase               = new GetReservationUseCase($this->reservationRepository);

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

        $this->useCase->execute(new GetReservationRequest(ReservationId::generate()));
    }

    public function testFoundReturnsCorrectReservationInResponse(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $response = $this->useCase->execute(new GetReservationRequest($this->reservation->id));

        $this->assertTrue($this->reservation->id->equals($response->reservation->id));
    }
}
