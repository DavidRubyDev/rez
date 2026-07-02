<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Mailer;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Mailer\MailerSettings;

class MailerSettingsTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $settings = new MailerSettings('info@studio.cz', 'Studio');

        $this->assertSame('info@studio.cz', $settings->fromAddress);
        $this->assertSame('Studio', $settings->fromName);
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailerSettings('not-an-email', 'Studio');
    }

    public function testEmptyFromNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MailerSettings('info@studio.cz', '');
    }
}
