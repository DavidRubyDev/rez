<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailVerificationRepositoryInterface;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\AuthTokenIssuer;
use Rez\Application\Service\EmailVerificationService;
use Rez\Application\Service\JwtService;
use Rez\Application\UseCase\Auth\VerifyEmail\VerifyEmailRequest;
use Rez\Application\UseCase\Auth\VerifyEmail\VerifyEmailUseCase;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeRequest;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeResponse;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Newsletter\NewsletterSubscriber;
use Rez\Domain\Newsletter\NewsletterSubscriberId;
use Rez\Domain\Newsletter\SubscriberSource;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

class VerifyEmailUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private EmailVerificationRepositoryInterface&MockObject $verificationRepository;
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;
    private SubscribeUseCaseInterface&MockObject $subscribeUseCase;
    private JwtService $jwtService;
    private VerifyEmailUseCase $useCase;
    private User $user;

    protected function setUp(): void
    {
        $this->userRepository         = $this->createMock(UserRepositoryInterface::class);
        $this->verificationRepository = $this->createMock(EmailVerificationRepositoryInterface::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->subscribeUseCase       = $this->createMock(SubscribeUseCaseInterface::class);
        $config                       = new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key');
        $this->jwtService             = new JwtService($config);
        $tokenGenerator               = $this->createMock(TokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('raw-refresh-token');

        $this->useCase = new VerifyEmailUseCase(
            $this->verificationRepository,
            $this->userRepository,
            new EmailVerificationService(
                $this->verificationRepository,
                $this->userRepository,
                $tokenGenerator,
                $this->createMock(MailerInterface::class),
                $this->subscribeUseCase,
                $config,
                new NullLogger(),
            ),
            new AuthTokenIssuer($this->jwtService, $this->refreshTokenRepository, $tokenGenerator, $config),
        );

        $this->user = $this->makeUser();
    }

    private function makeUser(bool $newsletterOptIn = false): User
    {
        return User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'), UserRole::Customer, $newsletterOptIn);
    }

    private function linkFor(User $user, string $expiresIn = '+1 hour'): void
    {
        $this->verificationRepository->method('findByTokenHash')
            ->with(hash('sha256', 'raw-verification-token'))
            ->willReturn(['user_id' => $user->id, 'expires_at' => Utc::now()->modify($expiresIn)]);
        $this->userRepository->method('findById')->willReturn($user);
    }

    private function verify(): \Rez\Application\UseCase\Auth\VerifyEmail\VerifyEmailResponse
    {
        return $this->useCase->execute(new VerifyEmailRequest('raw-verification-token'));
    }

    public function testUnknownTokenThrowsInvalidTokenException(): void
    {
        $this->verificationRepository->method('findByTokenHash')->willThrowException(new InvalidTokenException('Token not found'));

        $this->expectException(InvalidTokenException::class);

        $this->verify();
    }

    public function testLoadingTheTokenDatabaseExceptionIsWrapped(): void
    {
        $this->verificationRepository->method('findByTokenHash')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load email verification token.');

        $this->verify();
    }

    public function testExpiredTokenThrowsAndVerifiesNothing(): void
    {
        $this->linkFor($this->user, '-1 minute');
        $this->userRepository->expects($this->never())->method('save');
        $this->refreshTokenRepository->expects($this->never())->method('save');

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Token has expired');

        $this->verify();
    }

    public function testLoadingTheUserDatabaseExceptionIsWrapped(): void
    {
        $this->verificationRepository->method('findByTokenHash')
            ->willReturn(['user_id' => $this->user->id, 'expires_at' => Utc::now()->modify('+1 hour')]);
        $this->userRepository->method('findById')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load user.');

        $this->verify();
    }

    public function testValidTokenSavesTheUserVerifiedAndUsesUpTheLink(): void
    {
        $this->linkFor($this->user);
        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (User $user) => $user->id->equals($this->user->id) && $user->isEmailVerified()));
        $this->verificationRepository->expects($this->once())->method('deleteForUser')->with($this->user->id);

        $this->assertTrue($this->verify()->user->isEmailVerified());
    }

    public function testValidTokenSignsTheUserIn(): void
    {
        $this->linkFor($this->user);
        $this->refreshTokenRepository->expects($this->once())->method('save');

        $response = $this->verify();

        $this->assertTrue($this->user->id->equals($this->jwtService->extractUserId($response->tokens->accessToken)));
        $this->assertSame('raw-refresh-token', $response->tokens->refreshToken);
    }

    public function testVerifyingSubscribesAUserWhoOptedInAtRegistration(): void
    {
        $this->linkFor($this->makeUser(newsletterOptIn: true));
        $this->subscribeUseCase->expects($this->once())
            ->method('execute')
            ->with($this->callback(fn (SubscribeRequest $request) => $request->source === SubscriberSource::Registered))
            ->willReturn(new SubscribeResponse(NewsletterSubscriber::create(
                NewsletterSubscriberId::generate(),
                'john@example.com',
                'John Doe',
                SubscriberSource::Registered,
            )));

        $this->verify();
    }
}
