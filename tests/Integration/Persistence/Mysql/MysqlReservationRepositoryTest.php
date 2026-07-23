<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\NullLogger;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Session\SessionId;
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

        $this->repository = new MysqlReservationRepository($this->pdo(), new ReservationStatusMapper(), new NullLogger());
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

    public function testExternalRefIsPersistedAndHydrated(): void
    {
        $party = new Party('Jane Doe', 'jane@example.com', 1, null, 'user-uuid-123');
        $reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId]),
            new TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            $party,
        );

        $this->repository->save($reservation);
        $found = $this->repository->findById($reservation->id);

        $this->assertSame('user-uuid-123', $found->party->externalRef);
    }

    public function testNullExternalRefRoundtrips(): void
    {
        $reservation = $this->makeReservation();
        $this->repository->save($reservation);

        $found = $this->repository->findById($reservation->id);

        $this->assertNull($found->party->externalRef);
    }

    public function testCancelledReservationsAreExcludedFromOverlapQuery(): void
    {
        $slot = new TimeSlot(
            new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2024-01-15 11:00:00', new DateTimeZone('UTC')),
        );
        $reservation = $this->makeReservation($slot);
        $this->repository->save($reservation);

        $cancelled = $reservation->cancel();
        $this->repository->save($cancelled);

        $result = $this->repository->findByTimeSlotAndResource($slot, $this->resourceId);

        $this->assertSame(0, $result->count());
    }

    public function testCreatedAtIsHydratedAsUtc(): void
    {
        $reservation = $this->makeReservation();
        $this->repository->save($reservation);

        $found = $this->repository->findById($reservation->id);

        $this->assertSame('UTC', $found->createdAt->getTimezone()->getName());
    }

    public function testSlotTimestampsAreHydratedAsUtc(): void
    {
        $reservation = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2024-01-15 11:00:00', new DateTimeZone('UTC')),
        ));
        $this->repository->save($reservation);

        $found = $this->repository->findById($reservation->id);

        $this->assertSame('UTC', $found->slot->start->getTimezone()->getName());
        $this->assertSame('UTC', $found->slot->end->getTimezone()->getName());
    }

    public function testFindPageWithNoParamsMatchesFindAll(): void
    {
        $this->repository->save($this->makeReservation());
        $this->repository->save($this->makeReservation());

        $page = $this->repository->findPage();
        $all  = $this->repository->findAll();

        $this->assertSame($all->count(), $page->count());
        $this->assertSame(
            array_map(fn ($r) => $r->id->toString(), $all->toArray()),
            array_map(fn ($r) => $r->id->toString(), $page->toArray()),
        );
    }

    public function testFindPageFiltersByResourceIdWithoutDuplicatesWhenReservationHasMultipleResources(): void
    {
        $otherResourceId = ResourceId::generate();

        $multiResource = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId, $otherResourceId]),
            new TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            $this->party,
        );
        $unrelated = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-16 10:00:00'),
            new DateTimeImmutable('2024-01-16 11:00:00'),
        ));

        $this->repository->save($multiResource);
        $this->repository->save($unrelated);

        $page  = $this->repository->findPage(resourceId: $this->resourceId);
        $count = $this->repository->countPage(resourceId: $this->resourceId);

        $this->assertSame(2, $page->count());
        $this->assertSame(2, $count);
    }

    public function testFindPageFiltersByCreatedDateRange(): void
    {
        $inside = Reservation::reconstruct(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId]),
            new TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            $this->party,
            ReservationStatus::Pending,
            new DateTimeImmutable('2024-02-15 00:00:00'),
        );
        $outside = Reservation::reconstruct(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId]),
            new TimeSlot(
                new DateTimeImmutable('2024-01-16 10:00:00'),
                new DateTimeImmutable('2024-01-16 11:00:00'),
            ),
            $this->party,
            ReservationStatus::Pending,
            new DateTimeImmutable('2024-03-15 00:00:00'),
        );

        $this->repository->save($inside);
        $this->repository->save($outside);

        $result = $this->repository->findPage(
            createdFrom: new DateTimeImmutable('2024-02-01'),
            createdTo: new DateTimeImmutable('2024-02-29'),
        );
        $count = $this->repository->countPage(
            createdFrom: new DateTimeImmutable('2024-02-01'),
            createdTo: new DateTimeImmutable('2024-02-29'),
        );

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->toArray()[0]->id->equals($inside->id));
        $this->assertSame(1, $count);
    }

    public function testFindPageFiltersByStatus(): void
    {
        $pending = $this->makeReservation();
        $confirmed = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-16 10:00:00'),
            new DateTimeImmutable('2024-01-16 11:00:00'),
        ))->confirm();

        $this->repository->save($pending);
        $this->repository->save($confirmed);

        $result = $this->repository->findPage(status: ReservationStatus::Confirmed);

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->toArray()[0]->id->equals($confirmed->id));
    }

    public function testFindPageFiltersBySearchAgainstPartyFields(): void
    {
        $match = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId]),
            new TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            new Party('Alice Wonderland', 'alice@example.com', 1, null),
        );
        $noMatch = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-16 10:00:00'),
            new DateTimeImmutable('2024-01-16 11:00:00'),
        ));

        $this->repository->save($match);
        $this->repository->save($noMatch);

        $result = $this->repository->findPage(search: 'wonderland');

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->toArray()[0]->id->equals($match->id));
    }

    public function testFindPageSortsAndPaginates(): void
    {
        $first = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-10 10:00:00'),
            new DateTimeImmutable('2024-01-10 11:00:00'),
        ));
        $second = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-15 10:00:00'),
            new DateTimeImmutable('2024-01-15 11:00:00'),
        ));
        $third = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-20 10:00:00'),
            new DateTimeImmutable('2024-01-20 11:00:00'),
        ));

        $this->repository->save($first);
        $this->repository->save($second);
        $this->repository->save($third);

        $page = $this->repository->findPage(sortBy: 'start', sortDir: 'desc', offset: 1, limit: 1);

        $this->assertSame(1, $page->count());
        $this->assertTrue($page->toArray()[0]->id->equals($second->id));
    }

    public function testCountPageMatchesTrueDistinctCount(): void
    {
        $otherResourceId = ResourceId::generate();

        $multiResource = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId, $otherResourceId]),
            new TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            $this->party,
        );

        $this->repository->save($multiResource);

        $this->assertSame(1, $this->repository->countPage(resourceId: $this->resourceId));
    }

    public function testFindAllIsUnaffectedByFindPageChanges(): void
    {
        $this->repository->save($this->makeReservation());
        $this->repository->save($this->makeReservation());

        $this->assertSame(2, $this->repository->findAll()->count());
    }

    public function testSessionIdIsPersistedAndHydrated(): void
    {
        $sessionId   = SessionId::generate();
        $reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId]),
            new TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            $this->party,
            $sessionId,
        );

        $this->repository->save($reservation);
        $found = $this->repository->findById($reservation->id);

        $this->assertNotNull($found->sessionId);
        $this->assertTrue($sessionId->equals($found->sessionId));
    }

    public function testNullSessionIdRoundtrips(): void
    {
        $reservation = $this->makeReservation();
        $this->repository->save($reservation);

        $found = $this->repository->findById($reservation->id);

        $this->assertNull($found->sessionId);
    }

    public function testFindBySessionIdReturnsOnlyMatchingReservations(): void
    {
        $sessionId = SessionId::generate();
        $matching  = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([$this->resourceId]),
            new TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            $this->party,
            $sessionId,
        );
        $unrelated = $this->makeReservation(new TimeSlot(
            new DateTimeImmutable('2024-01-16 10:00:00'),
            new DateTimeImmutable('2024-01-16 11:00:00'),
        ));

        $this->repository->save($matching);
        $this->repository->save($unrelated);

        $result = $this->repository->findBySessionId($sessionId);

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->toArray()[0]->id->equals($matching->id));
    }
}
