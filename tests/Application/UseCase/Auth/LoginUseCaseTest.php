<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\AuthTokenIssuer;
use Rez\Application\Service\JwtService;
use Rez\Application\UseCase\Auth\Login\LoginRequest;
use Rez\Application\UseCase\Auth\Login\LoginUseCase;
use Rez\Domain\Exception\InvalidCredentialsException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;

class LoginUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;
    private JwtService $jwtService;
    private LoginUseCase $useCase;
    private User $user;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $config                       = new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key');
        $this->jwtService             = new JwtService($config);
        $tokenGenerator               = $this->createMock(TokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('raw-refresh-token');
        $issuer        = new AuthTokenIssuer($this->jwtService, $this->refreshTokenRepository, $tokenGenerator, $config);
        $this->useCase = new LoginUseCase($this->userRepository, $issuer);

        $this->user = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('correct-password'));
    }

    public function testUnknownEmailThrowsInvalidCredentialsException(): void
    {
        $this->userRepository->method('findByEmail')->willThrowException(new UserNotFoundException('john@example.com'));

        $this->expectException(InvalidCredentialsException::class);

        $this->useCase->execute(new LoginRequest('john@example.com', 'whatever'));
    }

    public function testFindByEmailDatabaseExceptionPropagates(): void
    {
        $this->userRepository->method('findByEmail')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load user.');

        $this->useCase->execute(new LoginRequest('john@example.com', 'whatever'));
    }

    public function testWrongPasswordThrowsInvalidCredentialsException(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);

        $this->expectException(InvalidCredentialsException::class);

        $this->useCase->execute(new LoginRequest('john@example.com', 'wrong-password'));
    }

    public function testSuccessReturnsUserAndAccessTokenForThatUser(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);

        $response = $this->useCase->execute(new LoginRequest('john@example.com', 'correct-password'));

        $this->assertSame($this->user, $response->user);
        $this->assertTrue($this->user->id->equals($this->jwtService->extractUserId($response->tokens->accessToken)));
    }

    public function testSuccessStartsANewRefreshTokenFamily(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->refreshTokenRepository->expects($this->once())->method('save');

        $response = $this->useCase->execute(new LoginRequest('john@example.com', 'correct-password'));

        $this->assertSame('raw-refresh-token', $response->tokens->refreshToken);
    }

    public function testWrongPasswordNeverIssuesARefreshToken(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->refreshTokenRepository->expects($this->never())->method('save');

        $this->expectException(InvalidCredentialsException::class);

        $this->useCase->execute(new LoginRequest('john@example.com', 'wrong-password'));
    }

    public function testRefreshTokenSaveDatabaseExceptionPropagates(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->refreshTokenRepository->method('save')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);

        $this->useCase->execute(new LoginRequest('john@example.com', 'correct-password'));
    }
}
