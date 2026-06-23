<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\CreditsConfig;

class CreditsConfigTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $config = new CreditsConfig(10000, 'CZK');

        $this->assertSame(10000, $config->minimumTopUpAmount);
        $this->assertSame('CZK', $config->currency);
    }

    public function testMinimumTopUpAmountBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CreditsConfig(0, 'CZK');
    }

    public function testEmptyCurrencyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CreditsConfig(10000, '');
    }
}
