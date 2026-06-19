<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Availability;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Resource\ResourceId;

class AvailabilityRuleTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $rule = new AvailabilityRule(ResourceId::generate(), 1, '09:00', '17:00');

        $this->assertSame(1, $rule->dayOfWeek());
        $this->assertSame('09:00', $rule->openTime());
        $this->assertSame('17:00', $rule->closeTime());
    }

    public function testDayOfWeekBelowZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AvailabilityRule(ResourceId::generate(), -1, '09:00', '17:00');
    }

    public function testDayOfWeekAboveSixThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AvailabilityRule(ResourceId::generate(), 7, '09:00', '17:00');
    }

    public function testCloseTimeBeforeOpenTimeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AvailabilityRule(ResourceId::generate(), 1, '17:00', '09:00');
    }

    public function testAppliesToDateReturnsTrueForMatchingDay(): void
    {
        // 2024-01-15 is a Monday (day 1)
        $rule = new AvailabilityRule(ResourceId::generate(), 1, '09:00', '17:00');

        $this->assertTrue($rule->appliesToDate(new DateTimeImmutable('2024-01-15')));
    }

    public function testAppliesToDateReturnsFalseForNonMatchingDay(): void
    {
        // 2024-01-15 is a Monday (day 1), rule is for Tuesday (day 2)
        $rule = new AvailabilityRule(ResourceId::generate(), 2, '09:00', '17:00');

        $this->assertFalse($rule->appliesToDate(new DateTimeImmutable('2024-01-15')));
    }

    public function testOpenTimeForDateReturnsCorrectDateTime(): void
    {
        $rule   = new AvailabilityRule(ResourceId::generate(), 1, '09:00', '17:00');
        $result = $rule->openTimeForDate(new DateTimeImmutable('2024-01-15'));

        $this->assertSame('2024-01-15 09:00', $result->format('Y-m-d H:i'));
    }

    public function testCloseTimeForDateReturnsCorrectDateTime(): void
    {
        $rule   = new AvailabilityRule(ResourceId::generate(), 1, '09:00', '17:00');
        $result = $rule->closeTimeForDate(new DateTimeImmutable('2024-01-15'));

        $this->assertSame('2024-01-15 17:00', $result->format('Y-m-d H:i'));
    }
}
