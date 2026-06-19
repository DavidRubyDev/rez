<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Shared;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rez\Domain\Exception\InvalidTimeSlotException;
use Rez\Domain\Shared\DateTimeRange;

class DateTimeRangeTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $range = new DateTimeRange(
            new DateTimeImmutable('2024-01-15 09:00:00'),
            new DateTimeImmutable('2024-01-15 17:00:00'),
        );

        $this->assertSame('2024-01-15 09:00:00', $range->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2024-01-15 17:00:00', $range->end()->format('Y-m-d H:i:s'));
    }

    public function testZeroDurationIsAllowed(): void
    {
        $dt    = new DateTimeImmutable('2024-01-15 09:00:00');
        $range = new DateTimeRange($dt, $dt);

        $this->assertSame($dt->getTimestamp(), $range->start()->getTimestamp());
    }

    public function testEndBeforeStartThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DateTimeRange(
            new DateTimeImmutable('2024-01-15 17:00:00'),
            new DateTimeImmutable('2024-01-15 09:00:00'),
        );
    }

    public function testContainsReturnsTrueForPointInsideRange(): void
    {
        $range = new DateTimeRange(
            new DateTimeImmutable('2024-01-15 09:00:00'),
            new DateTimeImmutable('2024-01-15 17:00:00'),
        );

        $this->assertTrue($range->contains(new DateTimeImmutable('2024-01-15 12:00:00')));
    }

    public function testContainsReturnsFalseForPointOutsideRange(): void
    {
        $range = new DateTimeRange(
            new DateTimeImmutable('2024-01-15 09:00:00'),
            new DateTimeImmutable('2024-01-15 17:00:00'),
        );

        $this->assertFalse($range->contains(new DateTimeImmutable('2024-01-15 18:00:00')));
    }

    public function testOverlapsWithReturnsTrueForOverlappingRanges(): void
    {
        $a = new DateTimeRange(
            new DateTimeImmutable('2024-01-15 09:00:00'),
            new DateTimeImmutable('2024-01-15 13:00:00'),
        );
        $b = new DateTimeRange(
            new DateTimeImmutable('2024-01-15 12:00:00'),
            new DateTimeImmutable('2024-01-15 17:00:00'),
        );

        $this->assertTrue($a->overlapsWith($b));
    }

    public function testOverlapsWithReturnsFalseForAdjacentRanges(): void
    {
        $a = new DateTimeRange(
            new DateTimeImmutable('2024-01-15 09:00:00'),
            new DateTimeImmutable('2024-01-15 12:00:00'),
        );
        $b = new DateTimeRange(
            new DateTimeImmutable('2024-01-15 12:00:00'),
            new DateTimeImmutable('2024-01-15 17:00:00'),
        );

        $this->assertFalse($a->overlapsWith($b));
    }

    public function testToTimeSlotReturnsTimeSlot(): void
    {
        $range    = new DateTimeRange(
            new DateTimeImmutable('2024-01-15 09:00:00'),
            new DateTimeImmutable('2024-01-15 17:00:00'),
        );
        $timeSlot = $range->toTimeSlot();

        $this->assertSame('2024-01-15 09:00:00', $timeSlot->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2024-01-15 17:00:00', $timeSlot->end()->format('Y-m-d H:i:s'));
    }

    public function testToTimeSlotThrowsForZeroDurationRange(): void
    {
        $dt    = new DateTimeImmutable('2024-01-15 09:00:00');
        $range = new DateTimeRange($dt, $dt);

        $this->expectException(InvalidTimeSlotException::class);
        $range->toTimeSlot();
    }
}
