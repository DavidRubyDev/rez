<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\PlanConfig;

class PlanConfigTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $plan = new PlanConfig('monthly', 'Měsíční členství', 99000, 'CZK', 30, 'price_1ABC');

        $this->assertSame('monthly', $plan->id);
        $this->assertSame('Měsíční členství', $plan->name);
        $this->assertSame(99000, $plan->priceAmount);
        $this->assertSame('CZK', $plan->currency);
        $this->assertSame(30, $plan->intervalDays);
        $this->assertSame('price_1ABC', $plan->stripePriceId);
    }

    public function testZeroPriceAmountIsValid(): void
    {
        $plan = new PlanConfig('free', 'Free Plan', 0, 'CZK', 30, 'price_free');

        $this->assertSame(0, $plan->priceAmount);
    }

    public function testEmptyIdThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlanConfig('', 'Měsíční členství', 99000, 'CZK', 30, 'price_1ABC');
    }

    public function testEmptyNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlanConfig('monthly', '', 99000, 'CZK', 30, 'price_1ABC');
    }

    public function testNegativePriceAmountThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlanConfig('monthly', 'Měsíční členství', -1, 'CZK', 30, 'price_1ABC');
    }

    public function testEmptyCurrencyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlanConfig('monthly', 'Měsíční členství', 99000, '', 30, 'price_1ABC');
    }

    public function testIntervalDaysBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlanConfig('monthly', 'Měsíční členství', 99000, 'CZK', 0, 'price_1ABC');
    }

    public function testEmptyStripePriceIdThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlanConfig('monthly', 'Měsíční členství', 99000, 'CZK', 30, '');
    }
}
