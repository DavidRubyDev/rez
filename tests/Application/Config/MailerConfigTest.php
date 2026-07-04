<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\MailerConfig;

class MailerConfigTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $config = new MailerConfig('https://example.com/cancel');

        $this->assertSame('https://example.com/cancel', $config->cancellationBaseUrl);
    }

    public function testEmptyCancellationBaseUrlThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailerConfig('');
    }
}
