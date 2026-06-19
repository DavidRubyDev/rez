<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\ListReservations;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsRequest;
use Rez\Application\UseCase\Reservation\ListReservations\ListReservationsUseCase;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class ListReservationsUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private ListReservationsUseCase $useCase;
    private ResourceId $resourceId;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->useCase               = new ListReservationsUseCase($this->reservationRepository);
        $this->resourceId            = ResourceId::generate();
    }

    private function makeReservation(ResourceId $resourceId): Reservation
    {
        return Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$resourceId]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );
    }

    public function testReturnsAllWhenNoFilters(): void
    {
        $collection = ReservationCollection::fromArray([
            $this->makeReservation($this->resourceId),
            $this->makeReservation(ResourceId::generate()),
        ]);

        $this->reservationRepository->method('findAll')->willReturn($collection);

        $response = $this->useCase->execute(new ListReservationsRequest());

        $this->assertSame(2, $response->reservations->count());
    }

    public function testFiltersByResourceIdInMemory(): void
    {
        $otherResourceId = ResourceId::generate();

        $collection = ReservationCollection::fromArray([
            $this->makeReservation($this->resourceId),
            $this->makeReservation($otherResourceId),
        ]);

        $this->reservationRepository->method('findAll')->willReturn($collection);

        $response = $this->useCase->execute(new ListReservationsRequest(resourceId: $this->resourceId));

        $this->assertSame(1, $response->reservations->count());
        $this->assertTrue($response->reservations->toArray()[0]->resourceIds()->contains($this->resourceId));
    }
}
