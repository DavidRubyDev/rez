<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Config;

use PHPUnit\Framework\TestCase;
use Rez\Application\Config\UsersConfig;

class UsersConfigTest extends TestCase
{
    public function testValidConstructionStoresValues(): void
    {
        $config = new UsersConfig('super-secret', 'super-secret-cancellation-key', 600, 30, 86400, 10);

        $this->assertSame('super-secret', $config->jwtSecret);
        $this->assertSame('super-secret-cancellation-key', $config->cancellationSecret);
        $this->assertSame(600, $config->accessTokenTtlSeconds);
        $this->assertSame(30, $config->passwordResetTtlMinutes);
        $this->assertSame(86400, $config->refreshTokenTtlSeconds);
        $this->assertSame(10, $config->refreshTokenReuseGraceSeconds);
    }

    public function testLoginThrottlingCanBeConfigured(): void
    {
        $config = new UsersConfig('super-secret', 'super-secret-cancellation-key', loginMaxFailedAttempts: 3, loginFailureWindowSeconds: 60);

        $this->assertSame(3, $config->loginMaxFailedAttempts);
        $this->assertSame(60, $config->loginFailureWindowSeconds);
    }

    public function testLoginMaxFailedAttemptsBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('super-secret', 'super-secret-cancellation-key', loginMaxFailedAttempts: 0);
    }

    public function testLoginFailureWindowBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('super-secret', 'super-secret-cancellation-key', loginFailureWindowSeconds: 0);
    }

    public function testDefaultsAreApplied(): void
    {
        $config = new UsersConfig('super-secret', 'super-secret-cancellation-key');

        $this->assertSame(900, $config->accessTokenTtlSeconds);
        $this->assertSame(60, $config->passwordResetTtlMinutes);
        $this->assertSame(2592000, $config->refreshTokenTtlSeconds);
        $this->assertSame(30, $config->refreshTokenReuseGraceSeconds);
        $this->assertSame(5, $config->loginMaxFailedAttempts);
        $this->assertSame(900, $config->loginFailureWindowSeconds);
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

    public function testAccessTokenTtlBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('super-secret', 'super-secret-cancellation-key', 0);
    }

    public function testPasswordResetTtlBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('super-secret', 'super-secret-cancellation-key', 3600, 0);
    }

    public function testRefreshTokenTtlBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('super-secret', 'super-secret-cancellation-key', refreshTokenTtlSeconds: 0);
    }

    public function testNegativeRefreshTokenReuseGraceThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UsersConfig('super-secret', 'super-secret-cancellation-key', refreshTokenReuseGraceSeconds: -1);
    }

    public function testZeroRefreshTokenReuseGraceIsAllowed(): void
    {
        $config = new UsersConfig('super-secret', 'super-secret-cancellation-key', refreshTokenReuseGraceSeconds: 0);

        $this->assertSame(0, $config->refreshTokenReuseGraceSeconds);
    }
}
