<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\PaymentsConfig;

class PaymentsConfigTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $config = new PaymentsConfig('CZK', 'whsec_test123');

        $this->assertSame('CZK', $config->currency);
        $this->assertSame('whsec_test123', $config->webhookSecret);
    }

    public function testEmptyCurrencyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PaymentsConfig('', 'whsec_test123');
    }

    public function testEmptyWebhookSecretThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PaymentsConfig('CZK', '');
    }
}
