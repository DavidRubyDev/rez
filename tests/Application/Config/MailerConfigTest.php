<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\MailerConfig;

class MailerConfigTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $config = new MailerConfig('info@studio.cz', 'Studio', 'super-secret-cancellation-key');

        $this->assertSame('info@studio.cz', $config->fromAddress);
        $this->assertSame('Studio', $config->fromName);
        $this->assertSame('super-secret-cancellation-key', $config->cancellationSecret);
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailerConfig('not-an-email', 'Studio', 'super-secret-cancellation-key');
    }

    public function testEmptyFromNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailerConfig('info@studio.cz', '', 'super-secret-cancellation-key');
    }

    public function testEmptyCancellationSecretThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailerConfig('info@studio.cz', 'Studio', '');
    }
}
