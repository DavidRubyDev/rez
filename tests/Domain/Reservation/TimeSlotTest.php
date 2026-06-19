<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Reservation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rez\Domain\Exception\InvalidTimeSlotException;
use Rez\Domain\Reservation\TimeSlot;

class TimeSlotTest extends TestCase
{
    private DateTimeImmutable $base;

    protected function setUp(): void
    {
        $this->base = new DateTimeImmutable('2024-01-15 10:00:00');
    }

    public function testValidConstructionSucceeds(): void
    {
        $start = $this->base;
        $end   = $this->base->modify('+1 hour');

        $slot = new TimeSlot($start, $end);

        $this->assertEquals($start, $slot->getStart());
        $this->assertEquals($end, $slot->getEnd());
    }

    public function testEndEqualToStartThrowsInvalidTimeSlotException(): void
    {
        $this->expectException(InvalidTimeSlotException::class);
        new TimeSlot($this->base, $this->base);
    }

    public function testEndBeforeStartThrowsInvalidTimeSlotException(): void
    {
        $this->expectException(InvalidTimeSlotException::class);
        new TimeSlot($this->base, $this->base->modify('-1 second'));
    }

    public function testOverlapsWithCompleteOverlap(): void
    {
        $a = new TimeSlot($this->base, $this->base->modify('+2 hours'));
        $b = new TimeSlot($this->base->modify('+30 minutes'), $this->base->modify('+90 minutes'));

        $this->assertTrue($a->overlapsWith($b));
        $this->assertTrue($b->overlapsWith($a));
    }

    public function testOverlapsWithPartialOverlapAtStart(): void
    {
        $a = new TimeSlot($this->base, $this->base->modify('+2 hours'));
        $b = new TimeSlot($this->base->modify('-1 hour'), $this->base->modify('+1 hour'));

        $this->assertTrue($a->overlapsWith($b));
    }

    public function testOverlapsWithPartialOverlapAtEnd(): void
    {
        $a = new TimeSlot($this->base, $this->base->modify('+2 hours'));
        $b = new TimeSlot($this->base->modify('+1 hour'), $this->base->modify('+3 hours'));

        $this->assertTrue($a->overlapsWith($b));
    }

    public function testAdjacentSlotsDoNotOverlap(): void
    {
        $a = new TimeSlot($this->base, $this->base->modify('+1 hour'));
        $b = new TimeSlot($this->base->modify('+1 hour'), $this->base->modify('+2 hours'));

        $this->assertFalse($a->overlapsWith($b));
        $this->assertFalse($b->overlapsWith($a));
    }

    public function testNoOverlap(): void
    {
        $a = new TimeSlot($this->base, $this->base->modify('+1 hour'));
        $b = new TimeSlot($this->base->modify('+2 hours'), $this->base->modify('+3 hours'));

        $this->assertFalse($a->overlapsWith($b));
        $this->assertFalse($b->overlapsWith($a));
    }

    public function testIdenticalSlotsOverlap(): void
    {
        $a = new TimeSlot($this->base, $this->base->modify('+1 hour'));
        $b = new TimeSlot($this->base, $this->base->modify('+1 hour'));

        $this->assertTrue($a->overlapsWith($b));
    }

    public function testDurationReturnsCorrectDateInterval(): void
    {
        $start = $this->base;
        $end   = $this->base->modify('+2 hours +30 minutes');
        $slot  = new TimeSlot($start, $end);

        $duration = $slot->duration();

        $this->assertSame(2, $duration->h);
        $this->assertSame(30, $duration->i);
    }

    public function testEqualsReturnsTrueForSameValues(): void
    {
        $a = new TimeSlot($this->base, $this->base->modify('+1 hour'));
        $b = new TimeSlot($this->base, $this->base->modify('+1 hour'));

        $this->assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentValues(): void
    {
        $a = new TimeSlot($this->base, $this->base->modify('+1 hour'));
        $b = new TimeSlot($this->base, $this->base->modify('+2 hours'));

        $this->assertFalse($a->equals($b));
    }

    public function testToStringFormat(): void
    {
        $start = new DateTimeImmutable('2024-01-15 10:00:00');
        $end   = new DateTimeImmutable('2024-01-15 11:30:00');
        $slot  = new TimeSlot($start, $end);

        $this->assertSame('2024-01-15 10:00:00 / 2024-01-15 11:30:00', (string) $slot);
    }
}
