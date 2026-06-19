<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Mapper;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Availability\DayOfWeek;
use Rez\Infrastructure\Mapper\DayOfWeekMapper;

class DayOfWeekMapperTest extends TestCase
{
    private DayOfWeekMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DayOfWeekMapper();
    }

    public function testToStringMapsAllDays(): void
    {
        $this->assertSame('monday', $this->mapper->toString(DayOfWeek::Monday));
        $this->assertSame('tuesday', $this->mapper->toString(DayOfWeek::Tuesday));
        $this->assertSame('wednesday', $this->mapper->toString(DayOfWeek::Wednesday));
        $this->assertSame('thursday', $this->mapper->toString(DayOfWeek::Thursday));
        $this->assertSame('friday', $this->mapper->toString(DayOfWeek::Friday));
        $this->assertSame('saturday', $this->mapper->toString(DayOfWeek::Saturday));
        $this->assertSame('sunday', $this->mapper->toString(DayOfWeek::Sunday));
    }

    public function testFromStringMapsAllDays(): void
    {
        $this->assertSame(DayOfWeek::Monday, $this->mapper->fromString('monday'));
        $this->assertSame(DayOfWeek::Tuesday, $this->mapper->fromString('tuesday'));
        $this->assertSame(DayOfWeek::Wednesday, $this->mapper->fromString('wednesday'));
        $this->assertSame(DayOfWeek::Thursday, $this->mapper->fromString('thursday'));
        $this->assertSame(DayOfWeek::Friday, $this->mapper->fromString('friday'));
        $this->assertSame(DayOfWeek::Saturday, $this->mapper->fromString('saturday'));
        $this->assertSame(DayOfWeek::Sunday, $this->mapper->fromString('sunday'));
    }

    public function testFromStringThrowsForUnknownValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mapper->fromString('funday');
    }
}
