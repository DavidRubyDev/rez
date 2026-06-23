<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\MailerConfig;

class MailerConfigTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $config = new MailerConfig('info@studio.cz', 'Studio');

        $this->assertSame('info@studio.cz', $config->fromAddress);
        $this->assertSame('Studio', $config->fromName);
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailerConfig('not-an-email', 'Studio');
    }

    public function testEmptyFromNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailerConfig('info@studio.cz', '');
    }
}
