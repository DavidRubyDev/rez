<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\LoginFailureRepositoryInterface;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\AuthTokenIssuer;
use Rez\Application\Service\JwtService;
use Rez\Application\UseCase\Auth\Login\LoginRequest;
use Rez\Application\UseCase\Auth\Login\LoginUseCase;
use Rez\Domain\Auth\LoginThrottleKey;
use Rez\Domain\Exception\InvalidCredentialsException;
use Rez\Domain\Exception\TooManyLoginAttemptsException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;

class LoginUseCaseTest extends TestCase
{
    private const CLIENT = '203.0.113.7';

    private UserRepositoryInterface&MockObject $userRepository;
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;
    private LoginFailureRepositoryInterface&MockObject $loginFailureRepository;
    private JwtService $jwtService;
    private LoginUseCase $useCase;
    private User $user;

    protected function setUp(): void
    {
        $this->userRepository         = $this->createMock(UserRepositoryInterface::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->loginFailureRepository = $this->createMock(LoginFailureRepositoryInterface::class);
        $config                       = new UsersConfig(
            'super-secret-jwt-at-least-32-bytes-long',
            'super-secret-cancellation-key',
            loginMaxFailedAttempts: 5,
            loginFailureWindowSeconds: 900,
        );
        $this->jwtService = new JwtService($config);
        $tokenGenerator   = $this->createMock(TokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('raw-refresh-token');
        $issuer        = new AuthTokenIssuer($this->jwtService, $this->refreshTokenRepository, $tokenGenerator, $config);
        $this->useCase = new LoginUseCase($this->userRepository, $issuer, $this->loginFailureRepository, $config);

        $this->user = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('correct-password'));
    }

    private function login(string $password, string $email = 'john@example.com'): void
    {
        $this->useCase->execute(new LoginRequest($email, $password, self::CLIENT));
    }

    /** @return list<DateTimeImmutable> */
    private function failuresSecondsAgo(int ...$secondsAgo): array
    {
        return array_values(array_map(fn (int $seconds) => Utc::now()->modify("-{$seconds} seconds"), $secondsAgo));
    }

    private function thisKey(): LoginThrottleKey
    {
        return LoginThrottleKey::for('john@example.com', self::CLIENT);
    }

    public function testUnknownEmailThrowsInvalidCredentialsException(): void
    {
        $this->userRepository->method('findByEmail')->willThrowException(new UserNotFoundException('john@example.com'));

        $this->expectException(InvalidCredentialsException::class);

        $this->login('whatever');
    }

    public function testFindByEmailDatabaseExceptionPropagates(): void
    {
        $this->userRepository->method('findByEmail')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load user.');

        $this->login('whatever');
    }

    public function testWrongPasswordThrowsInvalidCredentialsException(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);

        $this->expectException(InvalidCredentialsException::class);

        $this->login('wrong-password');
    }

    public function testSuccessReturnsUserAndAccessTokenForThatUser(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);

        $response = $this->useCase->execute(new LoginRequest('john@example.com', 'correct-password', self::CLIENT));

        $this->assertSame($this->user, $response->user);
        $this->assertTrue($this->user->id->equals($this->jwtService->extractUserId($response->tokens->accessToken)));
    }

    public function testSuccessStartsANewRefreshTokenFamily(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->refreshTokenRepository->expects($this->once())->method('save');

        $response = $this->useCase->execute(new LoginRequest('john@example.com', 'correct-password', self::CLIENT));

        $this->assertSame('raw-refresh-token', $response->tokens->refreshToken);
    }

    public function testWrongPasswordNeverIssuesARefreshToken(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->refreshTokenRepository->expects($this->never())->method('save');

        $this->expectException(InvalidCredentialsException::class);

        $this->login('wrong-password');
    }

    public function testRefreshTokenSaveDatabaseExceptionPropagates(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->refreshTokenRepository->method('save')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);

        $this->login('correct-password');
    }

    public function testWrongPasswordIsRecordedAgainstThisEmailAndClient(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->loginFailureRepository->expects($this->once())->method('record')
            ->with($this->callback(fn (LoginThrottleKey $key) => $key->equals($this->thisKey())));

        $this->expectException(InvalidCredentialsException::class);

        $this->login('wrong-password');
    }

    public function testUnknownEmailIsRecordedTheSameWay(): void
    {
        $this->userRepository->method('findByEmail')->willThrowException(new UserNotFoundException('john@example.com'));
        $this->loginFailureRepository->expects($this->once())->method('record')
            ->with($this->callback(fn (LoginThrottleKey $key) => $key->equals($this->thisKey())));

        $this->expectException(InvalidCredentialsException::class);

        $this->login('whatever');
    }

    public function testFailuresAreCountedOverTheConfiguredWindow(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $windowStart = Utc::now()->modify('-900 seconds');
        $this->loginFailureRepository->expects($this->once())->method('failedSince')
            ->with(
                $this->callback(fn (LoginThrottleKey $key) => $key->equals($this->thisKey())),
                $this->callback(fn (DateTimeImmutable $since) => abs($since->getTimestamp() - $windowStart->getTimestamp()) <= 1),
            )
            ->willReturn([]);

        $this->login('correct-password');
    }

    public function testFewerFailuresThanTheLimitStillAllowLogin(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->loginFailureRepository->method('failedSince')->willReturn($this->failuresSecondsAgo(500, 400, 300, 200));

        $response = $this->useCase->execute(new LoginRequest('john@example.com', 'correct-password', self::CLIENT));

        $this->assertSame($this->user, $response->user);
    }

    public function testReachingTheLimitRefusesEvenTheCorrectPassword(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->loginFailureRepository->method('failedSince')->willReturn($this->failuresSecondsAgo(500, 400, 300, 200, 100));
        $this->refreshTokenRepository->expects($this->never())->method('save');

        $this->expectException(TooManyLoginAttemptsException::class);

        $this->login('correct-password');
    }

    public function testARefusedAttemptIsNotRecordedAsAnotherFailure(): void
    {
        $this->loginFailureRepository->method('failedSince')->willReturn($this->failuresSecondsAgo(500, 400, 300, 200, 100));
        $this->loginFailureRepository->expects($this->never())->method('record');
        $this->userRepository->expects($this->never())->method('findByEmail');

        $this->expectException(TooManyLoginAttemptsException::class);

        $this->login('wrong-password');
    }

    public function testRetryAfterIsWhenTheOldestFailureLeavesTheWindow(): void
    {
        $this->loginFailureRepository->method('failedSince')->willReturn($this->failuresSecondsAgo(600, 400, 300, 200, 100));

        try {
            $this->login('correct-password');
            $this->fail('Expected TooManyLoginAttemptsException.');
        } catch (TooManyLoginAttemptsException $e) {
            $this->assertGreaterThanOrEqual(299, $e->retryAfterSeconds);
            $this->assertLessThanOrEqual(300, $e->retryAfterSeconds);
        }
    }

    public function testSuccessClearsTheFailuresForThisEmailAndClient(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->loginFailureRepository->method('failedSince')->willReturn($this->failuresSecondsAgo(300, 200));
        $this->loginFailureRepository->expects($this->once())->method('clear')
            ->with($this->callback(fn (LoginThrottleKey $key) => $key->equals($this->thisKey())));

        $this->login('correct-password');
    }

    public function testRecordingAFailureDatabaseExceptionPropagates(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->loginFailureRepository->method('record')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to record login failure.');

        $this->login('wrong-password');
    }

    public function testLoadingFailuresDatabaseExceptionPropagates(): void
    {
        $this->loginFailureRepository->method('failedSince')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load login failures.');

        $this->login('correct-password');
    }

    public function testClearingFailuresDatabaseExceptionPropagates(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->loginFailureRepository->method('clear')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to clear login failures.');

        $this->login('correct-password');
    }

    // Answering an unknown email without a password check would be measurably faster than a
    // wrong password for a real account — enough to tell which emails are registered.
    public function testUnknownEmailTakesAboutAsLongAsAWrongPassword(): void
    {
        $this->userRepository->method('findByEmail')->willReturnCallback(
            fn (string $email) => $email === 'john@example.com' ? $this->user : throw new UserNotFoundException($email),
        );

        $wrongPassword = $this->secondsToFailLogin('john@example.com');
        $unknownEmail  = $this->secondsToFailLogin('nobody@example.com');

        $this->assertGreaterThan($wrongPassword * 0.5, $unknownEmail);
    }

    private function secondsToFailLogin(string $email): float
    {
        $start = hrtime(true);
        try {
            $this->login('wrong-password', $email);
        } catch (InvalidCredentialsException) {
        }

        return (hrtime(true) - $start) / 1e9;
    }
}
