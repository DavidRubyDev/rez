<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use DateTimeImmutable;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Infrastructure\Mapper\ReservationStatusMapper;
use Rez\Infrastructure\Persistence\Mysql\MysqlReservationRepository;

class MysqlReservationRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlReservationRepository $repository;
    private ResourceId $resourceId;
    private Party $party;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MysqlReservationRepository($this->pdo(), new ReservationStatusMapper());
        $this->resourceId = ResourceId::generate();
        $this->party      = new Party('John Doe', 'john@example.com', 2, null);
    }

    private function makeReservation(?TimeSlot $slot = null): Reservation
    {
        return Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId]),
            $slot ?? new TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            $this->party,
        );
    }

    public function testSaveAndFindByIdRoundtrip(): void
    {
        $reservation = $this->makeReservation();
        $this->repository->save($reservation);

        $found = $this->repository->findById($reservation->id);

        $this->assertTrue($reservation->id->equals($found->id));
        $this->assertSame($reservation->status, $found->status);
        $this->assertTrue($found->resourceIds->contains($this->resourceId));
    }

    public function testFindByIdMissingThrowsReservationNotFoundException(): void
    {
        $this->expectException(ReservationNotFoundException::class);
        $this->repository->findById(ReservationId::generate());
    }

    public function testSaveUpdatesExistingReservation(): void
    {
        $reservation = $this->makeReservation();
        $this->repository->save($reservation);

        $confirmed = $reservation->confirm();
        $this->repository->save($confirmed);

        $found = $this->repository->findById($reservation->id);

        $this->assertSame($confirmed->status, $found->status);
    }

    public function testFindByTimeSlotAndResourceReturnsOverlappingReservations(): void
    {
        $overlapping = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
        ));
        $nonOverlapping = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-15 12:00:00'),
            new DateTimeImmutable('2024-01-15 13:00:00'),
        ));

        $this->repository->save($overlapping);
        $this->repository->save($nonOverlapping);

        $querySlot = new TimeSlot(
            new DateTimeImmutable('2024-01-15 10:30:00'),
            new DateTimeImmutable('2024-01-15 11:30:00'),
        );

        $result = $this->repository->findByTimeSlotAndResource($querySlot, $this->resourceId);

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->toArray()[0]->id->equals($overlapping->id));
    }

    public function testFindAllWithNoFiltersReturnsAll(): void
    {
        $this->repository->save($this->makeReservation());
        $this->repository->save($this->makeReservation());

        $result = $this->repository->findAll();

        $this->assertSame(2, $result->count());
    }

    public function testFindAllWithFromToFiltersCorrectly(): void
    {
        $inside = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
        ));
        $outside = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-20 10:00:00'),
            new DateTimeImmutable('2024-01-20 11:00:00'),
        ));

        $this->repository->save($inside);
        $this->repository->save($outside);

        $result = $this->repository->findAll(
            new DateTimeImmutable('2024-01-14'),
            new DateTimeImmutable('2024-01-16'),
        );

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->toArray()[0]->id->equals($inside->id));
    }
}
