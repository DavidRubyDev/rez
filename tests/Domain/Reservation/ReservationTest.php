<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Reservation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rez\Domain\Exception\DomainException;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;

class ReservationTest extends TestCase
{
    private ReservationId $id;
    private ResourceIdCollection $resourceIds;
    private TimeSlot $slot;
    private Party $party;

    protected function setUp(): void
    {
        $this->id          = ReservationId::generate();
        $this->resourceIds = ResourceIdCollection::fromArray([ResourceId::generate(), ResourceId::generate()]);
        $this->slot        = new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00'));
        $this->party       = new Party('John Doe', 'john@example.com', 2, null);
    }

    public function testCreateSetsPendingStatus(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);

        $this->assertSame(ReservationStatus::Pending, $reservation->getStatus());
    }

    public function testCreateSetsCreatedAtToApproximatelyNow(): void
    {
        $before      = new DateTimeImmutable();
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);
        $after       = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $reservation->getCreatedAt()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $reservation->getCreatedAt()->getTimestamp());
    }

    public function testCreateWithNoResourceIdsThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Reservation::create($this->id, ResourceIdCollection::empty(), $this->slot, $this->party);
    }

    public function testCreateStoresAllResourceIds(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);

        $this->assertSame(2, $reservation->getResourceIds()->count());
    }

    public function testConfirmFromPendingReturnsConfirmed(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);

        $this->assertSame(ReservationStatus::Confirmed, $reservation->confirm()->getStatus());
    }

    public function testConfirmFromConfirmedThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->confirm()->confirm();
    }

    public function testCancelFromPendingReturnsCancelled(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);

        $this->assertSame(ReservationStatus::Cancelled, $reservation->cancel()->getStatus());
    }

    public function testCancelFromConfirmedReturnsCancelled(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->confirm();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->cancel()->getStatus());
    }

    public function testCancelFromCancelledThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->cancel()->cancel();
    }

    public function testMarkNoShowFromConfirmedReturnsNoShow(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->confirm();

        $this->assertSame(ReservationStatus::NoShow, $reservation->markNoShow()->getStatus());
    }

    public function testMarkNoShowFromPendingThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->markNoShow();
    }

    public function testStateTransitionsReturnNewInstances(): void
    {
        $original  = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);
        $confirmed = $original->confirm();

        $this->assertNotSame($original, $confirmed);
        $this->assertSame(ReservationStatus::Pending, $original->getStatus());
        $this->assertSame(ReservationStatus::Confirmed, $confirmed->getStatus());
    }
}
