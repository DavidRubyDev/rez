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
use Rez\Application\UseCase\Auth\ResendEmailVerification\ResendEmailVerificationRequest;
use Rez\Application\UseCase\Auth\ResendEmailVerification\ResendEmailVerificationUseCase;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;

class ResendEmailVerificationUseCaseTest extends TestCase
{
    private const VERIFICATION_BASE_URL = 'https://studio.example/verify';

    private UserRepositoryInterface&MockObject $userRepository;
    private EmailVerificationRepositoryInterface&MockObject $verificationRepository;
    private MailerInterface&MockObject $mailer;
    private ResendEmailVerificationUseCase $useCase;
    private User $user;

    protected function setUp(): void
    {
        $this->userRepository         = $this->createMock(UserRepositoryInterface::class);
        $this->verificationRepository = $this->createMock(EmailVerificationRepositoryInterface::class);
        $this->mailer                 = $this->createMock(MailerInterface::class);
        $config                       = new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key', emailVerificationResendCooldownSeconds: 60);
        $tokenGenerator               = $this->createMock(TokenGeneratorInterface::class);
        $tokenGenerator->method('generate')->willReturn('raw-verification-token');

        $this->useCase = new ResendEmailVerificationUseCase(
            $this->userRepository,
            $this->verificationRepository,
            new EmailVerificationService(
                $this->verificationRepository,
                $this->userRepository,
                $tokenGenerator,
                $this->mailer,
                $this->createMock(SubscribeUseCaseInterface::class),
                $config,
                new NullLogger(),
            ),
            $config,
        );

        $this->user = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'));
    }

    private function resend(): bool
    {
        return $this->useCase->execute(new ResendEmailVerificationRequest('john@example.com', self::VERIFICATION_BASE_URL))->sent;
    }

    public function testUnknownEmailAnswersTheSameAndSendsNothing(): void
    {
        $this->userRepository->method('findByEmail')->willThrowException(new UserNotFoundException('john@example.com'));
        $this->mailer->expects($this->never())->method('sendEmailVerification');

        $this->assertTrue($this->resend());
    }

    public function testAlreadyVerifiedEmailAnswersTheSameAndSendsNothing(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user->withEmailVerifiedAt(Utc::now()));
        $this->mailer->expects($this->never())->method('sendEmailVerification');

        $this->assertTrue($this->resend());
    }

    public function testUnverifiedEmailWithNoOutstandingLinkGetsOne(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->verificationRepository->method('findIssuedAtForUser')->willReturn(null);
        $this->mailer->expects($this->once())
            ->method('sendEmailVerification')
            ->with('john@example.com', 'John Doe', self::VERIFICATION_BASE_URL . '?token=raw-verification-token');

        $this->assertTrue($this->resend());
    }

    public function testALinkSentWithinTheCooldownIsNotSentAgain(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->verificationRepository->method('findIssuedAtForUser')->willReturn(Utc::now()->modify('-59 seconds'));
        $this->mailer->expects($this->never())->method('sendEmailVerification');

        $this->assertTrue($this->resend());
    }

    public function testALinkOlderThanTheCooldownIsReplaced(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->verificationRepository->method('findIssuedAtForUser')->willReturn(Utc::now()->modify('-61 seconds'));
        $this->verificationRepository->expects($this->once())->method('save');
        $this->mailer->expects($this->once())->method('sendEmailVerification');

        $this->assertTrue($this->resend());
    }

    public function testFindByEmailDatabaseExceptionIsWrapped(): void
    {
        $this->userRepository->method('findByEmail')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load user.');

        $this->resend();
    }

    public function testLoadingTheOutstandingLinkDatabaseExceptionIsWrapped(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->verificationRepository->method('findIssuedAtForUser')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load email verification token.');

        $this->resend();
    }
}
