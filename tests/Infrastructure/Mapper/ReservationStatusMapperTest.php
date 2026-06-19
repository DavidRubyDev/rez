<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Mapper;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Reservation\ReservationStatus;
use Rez\Infrastructure\Mapper\ReservationStatusMapper;

class ReservationStatusMapperTest extends TestCase
{
    private ReservationStatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ReservationStatusMapper();
    }

    public function testPendingMapsToString(): void
    {
        $this->assertSame('pending', $this->mapper->toString(ReservationStatus::Pending));
    }

    public function testConfirmedMapsToString(): void
    {
        $this->assertSame('confirmed', $this->mapper->toString(ReservationStatus::Confirmed));
    }

    public function testCancelledMapsToString(): void
    {
        $this->assertSame('cancelled', $this->mapper->toString(ReservationStatus::Cancelled));
    }

    public function testNoShowMapsToString(): void
    {
        $this->assertSame('no_show', $this->mapper->toString(ReservationStatus::NoShow));
    }

    public function testStringMapsToPending(): void
    {
        $this->assertSame(ReservationStatus::Pending, $this->mapper->fromString('pending'));
    }

    public function testStringMapsToConfirmed(): void
    {
        $this->assertSame(ReservationStatus::Confirmed, $this->mapper->fromString('confirmed'));
    }

    public function testStringMapsToCancelled(): void
    {
        $this->assertSame(ReservationStatus::Cancelled, $this->mapper->fromString('cancelled'));
    }

    public function testStringMapsToNoShow(): void
    {
        $this->assertSame(ReservationStatus::NoShow, $this->mapper->fromString('no_show'));
    }

    public function testUnknownStringThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mapper->fromString('unknown');
    }
}
