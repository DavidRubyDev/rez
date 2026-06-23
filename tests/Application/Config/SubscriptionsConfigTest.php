<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\PlanConfig;
use Rez\Application\Config\SubscriptionsConfig;

class SubscriptionsConfigTest extends TestCase
{
    private PlanConfig $monthly;
    private PlanConfig $yearly;

    protected function setUp(): void
    {
        $this->monthly = new PlanConfig('monthly', 'Měsíční členství', 99000, 'CZK', 30, 'price_monthly');
        $this->yearly = new PlanConfig('yearly', 'Roční členství', 990000, 'CZK', 365, 'price_yearly');
    }

    public function testValidConstructionStoresPlans(): void
    {
        $config = new SubscriptionsConfig([$this->monthly, $this->yearly]);

        $this->assertSame([$this->monthly, $this->yearly], $config->plans);
    }

    public function testEmptyPlansArrayThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SubscriptionsConfig([]);
    }

    public function testNonPlanConfigElementThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SubscriptionsConfig([$this->monthly, 'not-a-plan']);
    }

    public function testGetPlanByIdReturnsMatchingPlan(): void
    {
        $config = new SubscriptionsConfig([$this->monthly, $this->yearly]);

        $this->assertSame($this->yearly, $config->getPlanById('yearly'));
    }

    public function testGetPlanByIdThrowsForUnknownId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $config = new SubscriptionsConfig([$this->monthly]);
        $config->getPlanById('unknown');
    }
}
