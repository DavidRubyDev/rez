<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Session;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rez\Domain\Exception\DomainException;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionId;
use Rez\Domain\Session\SessionStatus;

class SessionTest extends TestCase
{
    private SessionId $id;
    private ResourceId $resourceId;
    private DateTimeImmutable $startTime;

    protected function setUp(): void
    {
        $this->id         = SessionId::generate();
        $this->resourceId = ResourceId::generate();
        $this->startTime  = new DateTimeImmutable('2024-06-03 09:00:00');
    }

    public function testCreateSetsScheduledStatus(): void
    {
        $session = Session::create($this->id, $this->resourceId, $this->startTime, 60, 10);

        $this->assertSame(SessionStatus::Scheduled, $session->status);
    }

    public function testCreateStoresAllFields(): void
    {
        $session = Session::create($this->id, $this->resourceId, $this->startTime, 60, 10);

        $this->assertTrue($this->id->equals($session->id));
        $this->assertTrue($this->resourceId->equals($session->resourceId));
        $this->assertSame($this->startTime, $session->startTime);
        $this->assertSame(60, $session->durationMinutes);
        $this->assertSame(10, $session->capacity);
    }

    public function testCreateWithZeroDurationThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Session::create($this->id, $this->resourceId, $this->startTime, 0, 10);
    }

    public function testCreateWithNegativeDurationThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Session::create($this->id, $this->resourceId, $this->startTime, -30, 10);
    }

    public function testCreateWithZeroCapacityThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Session::create($this->id, $this->resourceId, $this->startTime, 60, 0);
    }

    public function testCreateWithNegativeCapacityThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Session::create($this->id, $this->resourceId, $this->startTime, 60, -1);
    }

    public function testCancelFromScheduledReturnsCancelled(): void
    {
        $session = Session::create($this->id, $this->resourceId, $this->startTime, 60, 10);

        $this->assertSame(SessionStatus::Cancelled, $session->cancel()->status);
    }

    public function testCancelFromCancelledThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        Session::create($this->id, $this->resourceId, $this->startTime, 60, 10)->cancel()->cancel();
    }

    public function testCancelReturnsNewInstance(): void
    {
        $original  = Session::create($this->id, $this->resourceId, $this->startTime, 60, 10);
        $cancelled = $original->cancel();

        $this->assertNotSame($original, $cancelled);
        $this->assertSame(SessionStatus::Scheduled, $original->status);
        $this->assertSame(SessionStatus::Cancelled, $cancelled->status);
    }

    public function testToTimeSlotDerivesEndFromDuration(): void
    {
        $session = Session::create($this->id, $this->resourceId, $this->startTime, 90, 10);

        $expected = new TimeSlot($this->startTime, new DateTimeImmutable('2024-06-03 10:30:00'));

        $this->assertTrue($expected->equals($session->toTimeSlot()));
    }

    public function testReconstructTrustsGivenStatus(): void
    {
        $session = Session::reconstruct($this->id, $this->resourceId, $this->startTime, 60, 10, SessionStatus::Cancelled);

        $this->assertSame(SessionStatus::Cancelled, $session->status);
    }
}
