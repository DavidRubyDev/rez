<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\AuthTokenIssuer;
use Rez\Application\Service\JwtService;
use Rez\Application\UseCase\Auth\Refresh\RefreshRequest;
use Rez\Application\UseCase\Auth\Refresh\RefreshResponse;
use Rez\Application\UseCase\Auth\Refresh\RefreshUseCase;
use Rez\Domain\Auth\RefreshToken;
use Rez\Domain\Auth\RefreshTokenFamilyId;
use Rez\Domain\Auth\RefreshTokenId;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

class RefreshUseCaseTest extends TestCase
{
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;
    private UserRepositoryInterface&MockObject $userRepository;
    private LoggerInterface&MockObject $logger;
    private JwtService $jwtService;
    private RefreshUseCase $useCase;
    private User $user;
    private RefreshTokenFamilyId $familyId;

    protected function setUp(): void
    {
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->userRepository         = $this->createMock(UserRepositoryInterface::class);
        $this->logger                 = $this->createMock(LoggerInterface::class);
        $this->jwtService             = new JwtService(new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key'));

        $this->user     = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'));
        $this->familyId = RefreshTokenFamilyId::generate();
        $this->userRepository->method('findById')->willReturn($this->user);

        $this->useCase = $this->buildUseCase();
    }

    private function buildUseCase(): RefreshUseCase
    {
        $config         = new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key', refreshTokenReuseGraceSeconds: 30);
        $tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('rotated-refresh-token');
        $issuer = new AuthTokenIssuer($this->jwtService, $this->refreshTokenRepository, $tokenGenerator, $config);

        return new RefreshUseCase($this->refreshTokenRepository, $this->userRepository, $issuer, $config, $this->logger);
    }

    private function storedToken(
        string $expiresIn = '+30 days',
        ?string $usedAgo = null,
        bool $revoked = false,
    ): RefreshToken {
        $now = Utc::now();

        return RefreshToken::reconstruct(
            RefreshTokenId::generate(),
            $this->familyId,
            $this->user->id,
            RefreshToken::hashOf('presented-token'),
            $now->modify($expiresIn),
            $now->modify('-1 day'),
            $usedAgo === null ? null : $now->modify("-{$usedAgo}"),
            $revoked ? $now : null,
        );
    }

    private function refresh(): RefreshResponse
    {
        return $this->useCase->execute(new RefreshRequest('presented-token'));
    }

    public function testLooksUpTheTokenByItsHash(): void
    {
        $this->refreshTokenRepository->expects($this->once())->method('findByTokenHash')
            ->with(RefreshToken::hashOf('presented-token'))
            ->willReturn($this->storedToken());

        $this->refresh();
    }

    public function testUnknownTokenThrowsInvalidTokenException(): void
    {
        $this->refreshTokenRepository->method('findByTokenHash')->willThrowException(new InvalidTokenException('Refresh token not found.'));

        $this->expectException(InvalidTokenException::class);

        $this->refresh();
    }

    public function testFindDatabaseExceptionPropagates(): void
    {
        $this->refreshTokenRepository->method('findByTokenHash')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load refresh token.');

        $this->refresh();
    }

    public function testRevokedTokenThrowsAndIssuesNothing(): void
    {
        $this->refreshTokenRepository->method('findByTokenHash')->willReturn($this->storedToken(revoked: true));
        $this->refreshTokenRepository->expects($this->never())->method('save');

        $this->expectException(InvalidTokenException::class);

        $this->refresh();
    }

    public function testExpiredTokenThrowsAndIssuesNothing(): void
    {
        $this->refreshTokenRepository->method('findByTokenHash')->willReturn($this->storedToken(expiresIn: '-1 second'));
        $this->refreshTokenRepository->expects($this->never())->method('save');

        $this->expectException(InvalidTokenException::class);

        $this->refresh();
    }

    public function testValidTokenIsMarkedUsedAndASuccessorIssuedInTheSameFamily(): void
    {
        $presented = $this->storedToken();
        $this->refreshTokenRepository->method('findByTokenHash')->willReturn($presented);

        $saved = [];
        $this->refreshTokenRepository->method('save')->willReturnCallback(function (RefreshToken $token) use (&$saved): void {
            $saved[] = $token;
        });

        $response = $this->refresh();

        $this->assertCount(2, $saved);
        $this->assertTrue($saved[0]->id->equals($presented->id));
        $this->assertTrue($saved[0]->isUsed());
        $this->assertFalse($saved[1]->id->equals($presented->id));
        $this->assertTrue($saved[1]->familyId->equals($this->familyId));
        $this->assertSame('rotated-refresh-token', $response->tokens->refreshToken);
        $this->assertSame($this->user, $response->user);
    }

    public function testAccessTokenCarriesTheUsersCurrentRole(): void
    {
        $promoted = $this->user->withRole(UserRole::Admin);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->method('findById')->willReturn($promoted);
        $this->refreshTokenRepository->method('findByTokenHash')->willReturn($this->storedToken());

        $response = $this->buildUseCase()->execute(new RefreshRequest('presented-token'));

        $this->assertSame(UserRole::Admin, $this->jwtService->extractRole($response->tokens->accessToken));
    }

    public function testReuseWithinTheGraceWindowIssuesASuccessorWithoutRevoking(): void
    {
        $this->refreshTokenRepository->method('findByTokenHash')->willReturn($this->storedToken(usedAgo: '10 seconds'));
        $this->refreshTokenRepository->expects($this->never())->method('revokeFamily');
        $this->refreshTokenRepository->expects($this->once())->method('save')->with($this->callback(
            fn (RefreshToken $token): bool => !$token->isUsed() && $token->familyId->equals($this->familyId),
        ));

        $response = $this->refresh();

        $this->assertSame('rotated-refresh-token', $response->tokens->refreshToken);
    }

    public function testReuseAfterTheGraceWindowRevokesTheWholeFamily(): void
    {
        $this->refreshTokenRepository->method('findByTokenHash')->willReturn($this->storedToken(usedAgo: '5 minutes'));
        $this->refreshTokenRepository->expects($this->once())->method('revokeFamily')->with($this->callback(
            fn (RefreshTokenFamilyId $familyId): bool => $familyId->equals($this->familyId),
        ));
        $this->refreshTokenRepository->expects($this->never())->method('save');
        $this->logger->expects($this->once())->method('warning');

        $this->expectException(InvalidTokenException::class);

        $this->refresh();
    }

    public function testDeletedUserThrowsInvalidTokenException(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->method('findById')->willThrowException(new UserNotFoundException('gone'));
        $this->refreshTokenRepository->method('findByTokenHash')->willReturn($this->storedToken());

        $this->expectException(InvalidTokenException::class);

        $this->buildUseCase()->execute(new RefreshRequest('presented-token'));
    }
}
