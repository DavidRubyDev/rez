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
use Rez\Domain\Session\SessionId;

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

        $this->assertSame(ReservationStatus::Pending, $reservation->status);
    }

    public function testCreateSetsCreatedAtToApproximatelyNow(): void
    {
        $before      = new DateTimeImmutable();
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);
        $after       = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $reservation->createdAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $reservation->createdAt->getTimestamp());
    }

    public function testCreateWithNoResourceIdsThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Reservation::create($this->id, ResourceIdCollection::empty(), $this->slot, $this->party);
    }

    public function testCreateStoresAllResourceIds(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);

        $this->assertSame(2, $reservation->resourceIds->count());
    }

    public function testConfirmFromPendingReturnsConfirmed(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);

        $this->assertSame(ReservationStatus::Confirmed, $reservation->confirm()->status);
    }

    public function testConfirmFromConfirmedThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->confirm()->confirm();
    }

    public function testCancelFromPendingReturnsCancelled(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);

        $this->assertSame(ReservationStatus::Cancelled, $reservation->cancel()->status);
    }

    public function testCancelFromConfirmedReturnsCancelled(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->confirm();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->cancel()->status);
    }

    public function testCancelFromCancelledThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->cancel()->cancel();
    }

    public function testMarkNoShowFromConfirmedReturnsNoShow(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->confirm();

        $this->assertSame(ReservationStatus::NoShow, $reservation->markNoShow()->status);
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
        $this->assertSame(ReservationStatus::Pending, $original->status);
        $this->assertSame(ReservationStatus::Confirmed, $confirmed->status);
    }

    public function testSessionIdDefaultsToNull(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);

        $this->assertNull($reservation->sessionId);
    }

    public function testSessionIdIsStoredAndReturned(): void
    {
        $sessionId   = SessionId::generate();
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party, $sessionId);

        $this->assertNotNull($reservation->sessionId);
        $this->assertTrue($sessionId->equals($reservation->sessionId));
    }

    public function testCreateSetsCheckedInToNull(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party);

        $this->assertNull($reservation->checkedIn);
    }

    public function testCheckInFromConfirmedReturnsCheckedInStatus(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->confirm();

        $this->assertSame(ReservationStatus::CheckedIn, $reservation->checkIn()->status);
    }

    public function testCheckInSetsCheckedInToApproximatelyNow(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->confirm();

        $before   = new DateTimeImmutable();
        $checkedIn = $reservation->checkIn();
        $after    = new DateTimeImmutable();

        $this->assertNotNull($checkedIn->checkedIn);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $checkedIn->checkedIn->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $checkedIn->checkedIn->getTimestamp());
    }

    public function testCheckInFromNoShowReturnsCheckedInStatus(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)
            ->confirm()
            ->markNoShow();

        $checkedIn = $reservation->checkIn();

        $this->assertSame(ReservationStatus::CheckedIn, $checkedIn->status);
        $this->assertNotNull($checkedIn->checkedIn);
    }

    public function testCheckInFromPendingThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->checkIn();
    }

    public function testCheckInFromCancelledThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->cancel()->checkIn();
    }

    public function testCheckInFromCheckedInThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->confirm()->checkIn()->checkIn();
    }

    public function testMarkNoShowFromCheckedInReturnsNoShowAndClearsCheckedIn(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)
            ->confirm()
            ->checkIn();

        $noShow = $reservation->markNoShow();

        $this->assertSame(ReservationStatus::NoShow, $noShow->status);
        $this->assertNull($noShow->checkedIn);
    }

    public function testMarkNoShowFromConfirmedLeavesCheckedInNull(): void
    {
        $reservation = Reservation::create($this->id, $this->resourceIds, $this->slot, $this->party)->confirm();

        $this->assertNull($reservation->markNoShow()->checkedIn);
    }
}
