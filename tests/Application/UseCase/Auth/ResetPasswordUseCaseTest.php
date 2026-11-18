<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Psr\Log\NullLogger;
use Rez\Application\Port\EmailVerificationRepositoryInterface;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\PasswordResetRepositoryInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Service\EmailVerificationService;
use Rez\Application\UseCase\Newsletter\Subscribe\SubscribeUseCaseInterface;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Service\PasswordPolicy;
use Rez\Domain\Exception\PasswordTooShortException;
use Rez\Application\UseCase\Auth\ResetPassword\ResetPasswordRequest;
use Rez\Application\UseCase\Auth\ResetPassword\ResetPasswordUseCase;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;

class ResetPasswordUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordResetRepositoryInterface&MockObject $passwordResetRepository;
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;
    private EmailVerificationRepositoryInterface&MockObject $verificationRepository;
    private ResetPasswordUseCase $useCase;
    private User $user;

    protected function setUp(): void
    {
        $this->userRepository          = $this->createMock(UserRepositoryInterface::class);
        $this->passwordResetRepository = $this->createMock(PasswordResetRepositoryInterface::class);
        $this->refreshTokenRepository  = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->verificationRepository  = $this->createMock(EmailVerificationRepositoryInterface::class);
        $config                        = new UsersConfig('super-secret', 'super-secret-cancellation-key');
        $this->useCase                 = new ResetPasswordUseCase(
            $this->userRepository,
            $this->passwordResetRepository,
            $this->refreshTokenRepository,
            new PasswordPolicy($config),
            new EmailVerificationService(
                $this->verificationRepository,
                $this->userRepository,
                $this->createMock(TokenGeneratorInterface::class),
                $this->createMock(MailerInterface::class),
                $this->createMock(SubscribeUseCaseInterface::class),
                $config,
                new NullLogger(),
            ),
        );

        $this->user = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('old-password'))
            ->withEmailVerifiedAt(Utc::now());
    }

    /** @return array{email: string, expires_at: \DateTimeImmutable} */
    private function record(\DateTimeImmutable $expiresAt): array
    {
        return ['email' => 'john@example.com', 'expires_at' => $expiresAt];
    }

    public function testPasswordTooShortThrowsAndLeavesTheTokenUsable(): void
    {
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record(Utc::now()->modify('+10 minutes')));
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->userRepository->expects($this->never())->method('save');
        $this->passwordResetRepository->expects($this->never())->method('deleteByEmail');
        $this->refreshTokenRepository->expects($this->never())->method('revokeAllForUser');

        $this->expectException(PasswordTooShortException::class);

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'fourteen-chars'));
    }

    // A dead link is the answer that matters — fixing the password first would only lead to it.
    public function testExpiredTokenWinsOverATooShortPassword(): void
    {
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record(Utc::now()->modify('-1 minute')));

        $this->expectException(InvalidTokenException::class);

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'short'));
    }

    public function testUnknownTokenThrowsInvalidTokenException(): void
    {
        $this->passwordResetRepository->method('findByTokenHash')->willThrowException(new InvalidTokenException('Token not found'));

        $this->expectException(InvalidTokenException::class);

        $this->useCase->execute(new ResetPasswordRequest('bad-token', 'new-password-long-enough'));
    }

    public function testFindByTokenHashDatabaseExceptionPropagates(): void
    {
        $this->passwordResetRepository->method('findByTokenHash')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password-long-enough'));
    }

    public function testExpiredTokenThrowsInvalidTokenException(): void
    {
        $expired = Utc::now()->modify('-1 minute');
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record($expired));

        $this->expectException(InvalidTokenException::class);

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password-long-enough'));
    }

    public function testSuccessSavesUserWithNewPasswordHash(): void
    {
        $valid = Utc::now()->modify('+10 minutes');
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record($valid));
        $this->userRepository->method('findByEmail')->willReturn($this->user);

        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (User $u) => $u->password->verify('new-password-long-enough') && !$u->password->verify('old-password')));

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password-long-enough'));
    }

    public function testSuccessDeletesTokenAfterUse(): void
    {
        $valid = Utc::now()->modify('+10 minutes');
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record($valid));
        $this->userRepository->method('findByEmail')->willReturn($this->user);

        $this->passwordResetRepository->expects($this->once())->method('deleteByEmail')->with('john@example.com');

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password-long-enough'));
    }

    public function testSuccessReturnsSuccessTrue(): void
    {
        $valid = Utc::now()->modify('+10 minutes');
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record($valid));
        $this->userRepository->method('findByEmail')->willReturn($this->user);

        $response = $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password-long-enough'));

        $this->assertTrue($response->success);
    }

    public function testSuccessRevokesEveryLoginOfThatUser(): void
    {
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record(Utc::now()->modify('+10 minutes')));
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->refreshTokenRepository->expects($this->once())->method('revokeAllForUser')->with($this->callback(
            fn (UserId $userId): bool => $userId->equals($this->user->id),
        ));

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password-long-enough'));
    }

    public function testExpiredTokenRevokesNothing(): void
    {
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record(Utc::now()->modify('-1 minute')));
        $this->refreshTokenRepository->expects($this->never())->method('revokeAllForUser');

        $this->expectException(InvalidTokenException::class);

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password-long-enough'));
    }

    public function testRevokeDatabaseExceptionPropagates(): void
    {
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record(Utc::now()->modify('+10 minutes')));
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->refreshTokenRepository->method('revokeAllForUser')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to revoke refresh tokens.');

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password-long-enough'));
    }

    public function testResettingTheUnverifiedEmailsPasswordVerifiesIt(): void
    {
        $unverified = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('old-password'));
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record(Utc::now()->modify('+10 minutes')));
        $this->userRepository->method('findByEmail')->willReturn($unverified);

        $saved = [];
        $this->userRepository->method('save')->willReturnCallback(function (User $user) use (&$saved): void {
            $saved[] = $user;
        });
        $this->verificationRepository->expects($this->once())->method('deleteForUser')->with($unverified->id);

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password-long-enough'));

        $last = end($saved);
        $this->assertInstanceOf(User::class, $last);
        $this->assertTrue($last->isEmailVerified());
        $this->assertTrue($last->password->verify('new-password-long-enough'));
    }

    public function testResettingAVerifiedEmailsPasswordLeavesItsVerificationAlone(): void
    {
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record(Utc::now()->modify('+10 minutes')));
        $this->userRepository->method('findByEmail')->willReturn($this->user);
        $this->verificationRepository->expects($this->never())->method('deleteForUser');

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password-long-enough'));
    }
}
