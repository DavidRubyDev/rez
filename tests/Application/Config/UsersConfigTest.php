<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\UsersConfig;

class UsersConfigTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $config = new UsersConfig('super-secret', 'super-secret-cancellation-key', 7200, 30);

        $this->assertSame('super-secret', $config->jwtSecret);
        $this->assertSame('super-secret-cancellation-key', $config->cancellationSecret);
        $this->assertSame(7200, $config->jwtTtlSeconds);
        $this->assertSame(30, $config->passwordResetTtlMinutes);
    }

    public function testDefaultsAreApplied(): void
    {
        $config = new UsersConfig('super-secret', 'super-secret-cancellation-key');

        $this->assertSame(3600, $config->jwtTtlSeconds);
        $this->assertSame(60, $config->passwordResetTtlMinutes);
    }

    public function testEmptyJwtSecretThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('', 'super-secret-cancellation-key');
    }

    public function testEmptyCancellationSecretThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('super-secret', '');
    }

    public function testJwtTtlBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('super-secret', 'super-secret-cancellation-key', 0);
    }

    public function testPasswordResetTtlBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('super-secret', 'super-secret-cancellation-key', 3600, 0);
    }
}
