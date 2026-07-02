<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\MailerConfig;

class MailerConfigTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $config = new MailerConfig('super-secret-cancellation-key');

        $this->assertSame('super-secret-cancellation-key', $config->cancellationSecret);
    }

    public function testEmptyCancellationSecretThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailerConfig('');
    }
}
