<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Availability;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rez\Domain\Availability\AvailabilityRule;
use Rez\Domain\Availability\DayOfWeek;
use Rez\Domain\Resource\ResourceId;

class AvailabilityRuleTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $rule = new AvailabilityRule(ResourceId::generate(), DayOfWeek::Monday, '09:00', '17:00');

        $this->assertSame(DayOfWeek::Monday, $rule->dayOfWeek);
        $this->assertSame('09:00', $rule->openTime);
        $this->assertSame('17:00', $rule->closeTime);
    }

    public function testCloseTimeBeforeOpenTimeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AvailabilityRule(ResourceId::generate(), DayOfWeek::Monday, '17:00', '09:00');
    }

    public function testAppliesToDateReturnsTrueForMatchingDay(): void
    {
        // 2024-01-15 is a Monday
        $rule = new AvailabilityRule(ResourceId::generate(), DayOfWeek::Monday, '09:00', '17:00');

        $this->assertTrue($rule->appliesToDate(new DateTimeImmutable('2024-01-15')));
    }

    public function testAppliesToDateReturnsFalseForNonMatchingDay(): void
    {
        // 2024-01-15 is a Monday, rule is for Tuesday
        $rule = new AvailabilityRule(ResourceId::generate(), DayOfWeek::Tuesday, '09:00', '17:00');

        $this->assertFalse($rule->appliesToDate(new DateTimeImmutable('2024-01-15')));
    }

    public function testOpenTimeForDateReturnsCorrectDateTime(): void
    {
        $rule   = new AvailabilityRule(ResourceId::generate(), DayOfWeek::Monday, '09:00', '17:00');
        $result = $rule->openTimeForDate(new DateTimeImmutable('2024-01-15'));

        $this->assertSame('2024-01-15 09:00', $result->format('Y-m-d H:i'));
    }

    public function testCloseTimeForDateReturnsCorrectDateTime(): void
    {
        $rule   = new AvailabilityRule(ResourceId::generate(), DayOfWeek::Monday, '09:00', '17:00');
        $result = $rule->closeTimeForDate(new DateTimeImmutable('2024-01-15'));

        $this->assertSame('2024-01-15 17:00', $result->format('Y-m-d H:i'));
    }

    public function testIsActiveOnReturnsTrueWithNoBounds(): void
    {
        $rule = new AvailabilityRule(ResourceId::generate(), DayOfWeek::Monday, '09:00', '17:00');

        $this->assertTrue($rule->isActiveOn(new DateTimeImmutable('2024-01-15')));
    }

    public function testIsActiveOnReturnsFalseWhenBeforeValidFrom(): void
    {
        $rule = new AvailabilityRule(
            ResourceId::generate(),
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validFrom: new DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')),
        );

        $this->assertFalse($rule->isActiveOn(new DateTimeImmutable('2023-12-31', new \DateTimeZone('UTC'))));
    }

    public function testIsActiveOnReturnsTrueWhenEqualToValidFrom(): void
    {
        $rule = new AvailabilityRule(
            ResourceId::generate(),
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validFrom: new DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')),
        );

        $this->assertTrue($rule->isActiveOn(new DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC'))));
    }

    public function testIsActiveOnReturnsTrueWhenAfterValidFrom(): void
    {
        $rule = new AvailabilityRule(
            ResourceId::generate(),
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validFrom: new DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')),
        );

        $this->assertTrue($rule->isActiveOn(new DateTimeImmutable('2024-06-15', new \DateTimeZone('UTC'))));
    }

    public function testIsActiveOnReturnsTrueWhenBeforeValidUntil(): void
    {
        $rule = new AvailabilityRule(
            ResourceId::generate(),
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validUntil: new DateTimeImmutable('2024-03-31', new \DateTimeZone('UTC')),
        );

        $this->assertTrue($rule->isActiveOn(new DateTimeImmutable('2024-01-15', new \DateTimeZone('UTC'))));
    }

    public function testIsActiveOnReturnsTrueWhenEqualToValidUntil(): void
    {
        $rule = new AvailabilityRule(
            ResourceId::generate(),
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validUntil: new DateTimeImmutable('2024-03-31', new \DateTimeZone('UTC')),
        );

        $this->assertTrue($rule->isActiveOn(new DateTimeImmutable('2024-03-31', new \DateTimeZone('UTC'))));
    }

    public function testIsActiveOnReturnsFalseWhenAfterValidUntil(): void
    {
        $rule = new AvailabilityRule(
            ResourceId::generate(),
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validUntil: new DateTimeImmutable('2024-03-31', new \DateTimeZone('UTC')),
        );

        $this->assertFalse($rule->isActiveOn(new DateTimeImmutable('2024-04-01', new \DateTimeZone('UTC'))));
    }

    public function testIsActiveOnReturnsTrueWhenDateInsideBothBounds(): void
    {
        $rule = new AvailabilityRule(
            ResourceId::generate(),
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validFrom:  new DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')),
            validUntil: new DateTimeImmutable('2024-03-31', new \DateTimeZone('UTC')),
        );

        $this->assertTrue($rule->isActiveOn(new DateTimeImmutable('2024-02-15', new \DateTimeZone('UTC'))));
    }

    public function testIsActiveOnReturnsFalseWhenDateOutsideBothBounds(): void
    {
        $rule = new AvailabilityRule(
            ResourceId::generate(),
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validFrom:  new DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')),
            validUntil: new DateTimeImmutable('2024-03-31', new \DateTimeZone('UTC')),
        );

        $this->assertFalse($rule->isActiveOn(new DateTimeImmutable('2024-05-01', new \DateTimeZone('UTC'))));
    }

    public function testIsActiveOnIgnoresTimeComponent(): void
    {
        $rule = new AvailabilityRule(
            ResourceId::generate(),
            DayOfWeek::Monday,
            '09:00',
            '17:00',
            validFrom:  new DateTimeImmutable('2024-01-15', new \DateTimeZone('UTC')),
            validUntil: new DateTimeImmutable('2024-01-15', new \DateTimeZone('UTC')),
        );

        $this->assertTrue($rule->isActiveOn(new DateTimeImmutable('2024-01-15 00:00:00', new \DateTimeZone('UTC'))));
        $this->assertTrue($rule->isActiveOn(new DateTimeImmutable('2024-01-15 23:59:59', new \DateTimeZone('UTC'))));
    }

    public function testOpenTimeForDateReturnsUtcTimezone(): void
    {
        $rule   = new AvailabilityRule(ResourceId::generate(), DayOfWeek::Monday, '09:00', '17:00');
        $result = $rule->openTimeForDate(new DateTimeImmutable('2024-01-15', new \DateTimeZone('UTC')));

        $this->assertSame('UTC', $result->getTimezone()->getName());
    }

    public function testCloseTimeForDateReturnsUtcTimezone(): void
    {
        $rule   = new AvailabilityRule(ResourceId::generate(), DayOfWeek::Monday, '09:00', '17:00');
        $result = $rule->closeTimeForDate(new DateTimeImmutable('2024-01-15', new \DateTimeZone('UTC')));

        $this->assertSame('UTC', $result->getTimezone()->getName());
    }
}
