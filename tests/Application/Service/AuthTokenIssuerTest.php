<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Service\AuthTokenIssuer;
use Rez\Application\Service\JwtService;
use Rez\Domain\Auth\RefreshToken;
use Rez\Domain\Auth\RefreshTokenFamilyId;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

class AuthTokenIssuerTest extends TestCase
{
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;
    private TokenGeneratorInterface&MockObject $tokenGenerator;
    private JwtService $jwtService;
    private AuthTokenIssuer $issuer;
    private User $user;

    protected function setUp(): void
    {
        $config = new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key', accessTokenTtlSeconds: 600, refreshTokenTtlSeconds: 86400);

        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->tokenGenerator         = $this->createMock(TokenGeneratorInterface::class);
        $this->tokenGenerator->method('generate')->willReturn('raw-refresh-token');
        $this->jwtService = new JwtService($config);
        $this->issuer     = new AuthTokenIssuer($this->jwtService, $this->refreshTokenRepository, $this->tokenGenerator, $config);

        $this->user = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('correct-password'), UserRole::Admin);
    }

    public function testAccessTokenIdentifiesTheUserAndRole(): void
    {
        $tokens = $this->issuer->issue($this->user);

        $this->assertTrue($this->user->id->equals($this->jwtService->extractUserId($tokens->accessToken)));
        $this->assertSame(UserRole::Admin, $this->jwtService->extractRole($tokens->accessToken));
    }

    public function testExpiriesFollowTheConfiguredLifetimes(): void
    {
        $before = Utc::now();

        $tokens = $this->issuer->issue($this->user);

        $this->assertEqualsWithDelta($before->getTimestamp() + 600, $tokens->accessTokenExpiresAt->getTimestamp(), 2);
        $this->assertEqualsWithDelta($before->getTimestamp() + 86400, $tokens->refreshTokenExpiresAt->getTimestamp(), 2);
    }

    public function testReturnsTheRawRefreshTokenAndStoresOnlyItsHash(): void
    {
        $this->refreshTokenRepository->expects($this->once())->method('save')->with($this->callback(
            fn (RefreshToken $token): bool => $token->tokenHash === RefreshToken::hashOf('raw-refresh-token')
                && $token->userId->equals($this->user->id),
        ));

        $tokens = $this->issuer->issue($this->user);

        $this->assertSame('raw-refresh-token', $tokens->refreshToken);
    }

    public function testStartsANewFamilyWhenNoneIsGiven(): void
    {
        $saved = [];
        $this->refreshTokenRepository->method('save')->willReturnCallback(function (RefreshToken $token) use (&$saved): void {
            $saved[] = $token;
        });

        $this->issuer->issue($this->user);
        $this->issuer->issue($this->user);

        $this->assertCount(2, $saved);
        $this->assertFalse($saved[0]->familyId->equals($saved[1]->familyId));
    }

    public function testContinuesTheGivenFamily(): void
    {
        $familyId = RefreshTokenFamilyId::generate();

        $this->refreshTokenRepository->expects($this->once())->method('save')->with($this->callback(
            fn (RefreshToken $token): bool => $token->familyId->equals($familyId),
        ));

        $this->issuer->issue($this->user, $familyId);
    }

    public function testSaveDatabaseExceptionPropagates(): void
    {
        $this->refreshTokenRepository->method('save')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save refresh token.');

        $this->issuer->issue($this->user);
    }
}
