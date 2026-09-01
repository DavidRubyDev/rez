<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\MailerConfig;

class MailerConfigTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $config = new MailerConfig('https://example.com/cancel', 'https://example.com/unsubscribe');

        $this->assertSame('https://example.com/cancel', $config->cancellationBaseUrl);
        $this->assertSame('https://example.com/unsubscribe', $config->unsubscribeBaseUrl);
    }

    public function testEmptyCancellationBaseUrlThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailerConfig('', 'https://example.com/unsubscribe');
    }

    public function testEmptyUnsubscribeBaseUrlThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailerConfig('https://example.com/cancel', '');
    }
}
