<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Reservation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationCollection;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class ReservationCollectionTest extends TestCase
{
    private function makeReservation(): Reservation
    {
        return Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );
    }

    public function testEmptyCreatesEmptyCollection(): void
    {
        $collection = ReservationCollection::empty();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
    }

    public function testAddReturnsNewInstanceWithElement(): void
    {
        $collection = ReservationCollection::empty()->add($this->makeReservation());

        $this->assertSame(1, $collection->count());
    }

    public function testOriginalUnchangedAfterAdd(): void
    {
        $collection = ReservationCollection::empty();
        $collection->add($this->makeReservation());

        $this->assertTrue($collection->isEmpty());
    }

    public function testFilterReturnsMatchingSubset(): void
    {
        $pending   = $this->makeReservation();
        $confirmed = $this->makeReservation()->confirm();

        $collection = ReservationCollection::empty()->add($pending)->add($confirmed);
        $filtered   = $collection->filter(fn (Reservation $r) => $r->status() === ReservationStatus::Confirmed);

        $this->assertSame(1, $filtered->count());
    }

    public function testFilterByStatusReturnsMatchingSubset(): void
    {
        $pending   = $this->makeReservation();
        $confirmed = $this->makeReservation()->confirm();

        $collection = ReservationCollection::empty()->add($pending)->add($confirmed);
        $filtered   = $collection->filterByStatus(ReservationStatus::Pending);

        $this->assertSame(1, $filtered->count());
        $this->assertSame(ReservationStatus::Pending, $filtered->toArray()[0]->status());
    }

    public function testFindByIdReturnsCorrectReservation(): void
    {
        $reservation = $this->makeReservation();
        $collection  = ReservationCollection::empty()->add($reservation);

        $found = $collection->findById($reservation->id());

        $this->assertNotNull($found);
        $this->assertTrue($reservation->id()->equals($found->id()));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $collection = ReservationCollection::empty();

        $this->assertNull($collection->findById(ReservationId::generate()));
    }
}
