<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Service;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Service\JwtService;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

class JwtServiceTest extends TestCase
{
    private JwtService $service;
    private UsersConfig $config;

    protected function setUp(): void
    {
        $this->config  = new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key');
        $this->service = new JwtService($this->config);
    }

    protected function tearDown(): void
    {
        JWT::$timestamp = null;
    }

    public function testGenerateReturnsNonEmptyString(): void
    {
        $token = $this->service->generate(UserId::generate(), UserRole::Customer);

        $this->assertNotSame('', $token);
    }

    public function testValidateRoundtripsGeneratedToken(): void
    {
        $userId = UserId::generate();
        $token  = $this->service->generate($userId, UserRole::Admin);

        $payload = $this->service->validate($token);

        $this->assertSame($userId->toString(), $payload['sub']);
        $this->assertSame('Admin', $payload['role']);
    }

    public function testValidateWithTamperedTokenThrowsInvalidTokenException(): void
    {
        $token = $this->service->generate(UserId::generate(), UserRole::Customer);

        $this->expectException(InvalidTokenException::class);
        $this->service->validate($token . 'tampered');
    }

    public function testValidateWithExpiredTokenThrowsInvalidTokenException(): void
    {
        $shortLivedConfig = new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key', jwtTtlSeconds: 1);
        $service          = new JwtService($shortLivedConfig);
        $token            = $service->generate(UserId::generate(), UserRole::Customer);

        JWT::$timestamp = time() + 10;

        $this->expectException(InvalidTokenException::class);
        $service->validate($token);
    }

    public function testExtractUserIdReturnsCorrectUserId(): void
    {
        $userId = UserId::generate();
        $token  = $this->service->generate($userId, UserRole::Customer);

        $this->assertTrue($userId->equals($this->service->extractUserId($token)));
    }

    public function testExtractRoleReturnsCorrectUserRole(): void
    {
        $token = $this->service->generate(UserId::generate(), UserRole::Admin);

        $this->assertSame(UserRole::Admin, $this->service->extractRole($token));
    }

    public function testExtractRoleWithUnknownRoleStringThrowsInvalidTokenException(): void
    {
        $token = JWT::encode(
            ['sub' => UserId::generate()->toString(), 'role' => 'Superuser', 'iat' => time(), 'exp' => time() + 3600],
            $this->config->jwtSecret,
            'HS256',
        );

        $this->expectException(InvalidTokenException::class);
        $this->service->extractRole($token);
    }
}
