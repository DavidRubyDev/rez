<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\UsersConfig;

class UsersConfigTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $config = new UsersConfig('super-secret', 7200, 30);

        $this->assertSame('super-secret', $config->jwtSecret);
        $this->assertSame(7200, $config->jwtTtlSeconds);
        $this->assertSame(30, $config->passwordResetTtlMinutes);
    }

    public function testDefaultsAreApplied(): void
    {
        $config = new UsersConfig('super-secret');

        $this->assertSame(3600, $config->jwtTtlSeconds);
        $this->assertSame(60, $config->passwordResetTtlMinutes);
    }

    public function testEmptyJwtSecretThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('');
    }

    public function testJwtTtlBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('super-secret', 0);
    }

    public function testPasswordResetTtlBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('super-secret', 3600, 0);
    }
}
