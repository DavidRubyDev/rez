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
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Service\EmailVerificationService;
use Rez\Application\Service\PasswordPolicy;
use Rez\Application\UseCase\Auth\Register\RegisterRequest;
use Rez\Application\UseCase\Auth\Register\RegisterUseCase;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Domain\Exception\EmailAlreadyRegisteredException;
use Rez\Domain\Exception\PasswordTooShortException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;

class RegisterUseCaseTest extends TestCase
{
    private const VERIFICATION_BASE_URL = 'https://studio.example/verify';

    private UserRepositoryInterface&MockObject $userRepository;
    private EmailVerificationRepositoryInterface&MockObject $verificationRepository;
    private MailerInterface&MockObject $mailer;
    private SubscribeUseCaseInterface&MockObject $subscribeUseCase;
    private UsersConfig $config;

    protected function setUp(): void
    {
        $this->userRepository         = $this->createMock(UserRepositoryInterface::class);
        $this->verificationRepository = $this->createMock(EmailVerificationRepositoryInterface::class);
        $this->mailer                 = $this->createMock(MailerInterface::class);
        $this->subscribeUseCase       = $this->createMock(SubscribeUseCaseInterface::class);
        $this->config                 = new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key');

        $this->userRepository->method('findByEmail')->willThrowException(new UserNotFoundException('john@example.com'));
    }

    private function useCase(): RegisterUseCase
    {
        $tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('raw-verification-token');

        return new RegisterUseCase(
            $this->userRepository,
            new PasswordPolicy($this->config),
            new EmailVerificationService(
                $this->verificationRepository,
                $this->userRepository,
                $tokenGenerator,
                $this->mailer,
                $this->subscribeUseCase,
                $this->config,
                new NullLogger(),
            ),
        );
    }

    private function request(bool $newsletterOptIn = false, string $password = 'correct-horse-battery-staple'): RegisterRequest
    {
        return new RegisterRequest('John Doe', 'john@example.com', $password, self::VERIFICATION_BASE_URL, $newsletterOptIn);
    }

    public function testPasswordTooShortThrowsAndSavesNothing(): void
    {
        $this->userRepository->expects($this->never())->method('save');
        $this->mailer->expects($this->never())->method('sendEmailVerification');

        $this->expectException(PasswordTooShortException::class);

        $this->useCase()->execute($this->request(password: 'fourteen-chars'));
    }

    public function testPasswordIsCheckedBeforeTheEmailIsLookedUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->expects($this->never())->method('findByEmail');

        $this->expectException(PasswordTooShortException::class);

        $this->useCase()->execute($this->request(password: 'short'));
    }

    public function testDuplicateEmailThrowsEmailAlreadyRegisteredException(): void
    {
        $existing             = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'));
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->method('findByEmail')->willReturn($existing);
        $this->mailer->expects($this->never())->method('sendEmailVerification');

        $this->expectException(EmailAlreadyRegisteredException::class);

        $this->useCase()->execute($this->request());
    }

    public function testFindByEmailDatabaseExceptionPropagates(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userRepository->method('findByEmail')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to check for existing user.');

        $this->useCase()->execute($this->request());
    }

    public function testSavesTheUserOnceWithAnUnverifiedEmail(): void
    {
        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (User $user) => !$user->isEmailVerified()));

        $this->useCase()->execute($this->request());
    }

    public function testEmailsAVerificationLink(): void
    {
        $this->verificationRepository->expects($this->once())->method('save');
        $this->mailer->expects($this->once())
            ->method('sendEmailVerification')
            ->with('john@example.com', 'John Doe', self::VERIFICATION_BASE_URL . '?token=raw-verification-token');

        $this->useCase()->execute($this->request());
    }

    public function testNoLinkIsSentWhenTheUserCannotBeSaved(): void
    {
        $this->userRepository->method('save')->willThrowException(new DatabaseException('pdo error'));
        $this->mailer->expects($this->never())->method('sendEmailVerification');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to save user.');

        $this->useCase()->execute($this->request());
    }

    public function testNewsletterOptInIsKeptButNotActedOnUntilTheEmailIsVerified(): void
    {
        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (User $user) => $user->newsletterOptIn));
        $this->subscribeUseCase->expects($this->never())->method('execute');

        $this->useCase()->execute($this->request(newsletterOptIn: true));
    }

    public function testReturnsTheNewUnverifiedUser(): void
    {
        $response = $this->useCase()->execute($this->request());

        $this->assertSame('John Doe', $response->user->name);
        $this->assertSame('john@example.com', $response->user->email);
        $this->assertFalse($response->user->isEmailVerified());
    }
}
