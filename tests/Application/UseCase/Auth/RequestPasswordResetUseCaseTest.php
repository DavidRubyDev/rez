<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Config\UsersConfig;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Port\PasswordResetRepositoryInterface;
use Rez\Application\Port\TokenGeneratorInterface;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\UseCase\Auth\RequestPasswordReset\RequestPasswordResetRequest;
use Rez\Application\UseCase\Auth\RequestPasswordReset\RequestPasswordResetUseCase;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;

class RequestPasswordResetUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordResetRepositoryInterface&MockObject $passwordResetRepository;
    private TokenGeneratorInterface&MockObject $tokenGenerator;
    private MailerInterface&MockObject $mailer;
    private UsersConfig $config;
    private RequestPasswordResetUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository         = $this->createMock(UserRepositoryInterface::class);
        $this->passwordResetRepository = $this->createMock(PasswordResetRepositoryInterface::class);
        $this->tokenGenerator         = $this->createMock(TokenGeneratorInterface::class);
        $this->mailer                 = $this->createMock(MailerInterface::class);
        $this->config                 = new UsersConfig('super-secret-jwt-at-least-32-bytes-long', 'super-secret-cancellation-key');

        $this->useCase = new RequestPasswordResetUseCase(
            $this->userRepository,
            $this->passwordResetRepository,
            $this->tokenGenerator,
            $this->mailer,
            $this->config,
        );
    }

    public function testUnknownEmailReturnsSentTrueWithoutSavingOrMailing(): void
    {
        $this->userRepository->method('findByEmail')->willThrowException(new UserNotFoundException('unknown@example.com'));
        $this->passwordResetRepository->expects($this->never())->method('save');
        $this->mailer->expects($this->never())->method('sendPasswordReset');

        $response = $this->useCase->execute(new RequestPasswordResetRequest('unknown@example.com', 'https://app.test/reset'));

        $this->assertTrue($response->sent);
    }

    public function testFindByEmailDatabaseExceptionPropagates(): void
    {
        $this->userRepository->method('findByEmail')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);

        $this->useCase->execute(new RequestPasswordResetRequest('john@example.com', 'https://app.test/reset'));
    }

    public function testKnownEmailSavesTokenHashOnce(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user());
        $this->tokenGenerator->method('generate')->willReturn('raw-token-value');

        $this->passwordResetRepository->expects($this->once())
            ->method('save')
            ->with('john@example.com', hash('sha256', 'raw-token-value'), $this->isInstanceOf(\DateTimeImmutable::class));

        $this->useCase->execute(new RequestPasswordResetRequest('john@example.com', 'https://app.test/reset'));
    }

    public function testKnownEmailStoresHashNotRawToken(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user());
        $this->tokenGenerator->method('generate')->willReturn('raw-token-value');

        $this->passwordResetRepository->expects($this->once())
            ->method('save')
            ->with(
                $this->anything(),
                $this->callback(fn (string $hash) => $hash !== 'raw-token-value'),
                $this->anything(),
            );

        $this->useCase->execute(new RequestPasswordResetRequest('john@example.com', 'https://app.test/reset'));
    }

    public function testKnownEmailMailerCalledWithUrlContainingRawToken(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user());
        $this->tokenGenerator->method('generate')->willReturn('raw-token-value');

        $this->mailer->expects($this->once())
            ->method('sendPasswordReset')
            ->with('john@example.com', $this->stringContains('raw-token-value'));

        $this->useCase->execute(new RequestPasswordResetRequest('john@example.com', 'https://app.test/reset'));
    }

    public function testKnownEmailReturnsSentTrue(): void
    {
        $this->userRepository->method('findByEmail')->willReturn($this->user());
        $this->tokenGenerator->method('generate')->willReturn('raw-token-value');

        $response = $this->useCase->execute(new RequestPasswordResetRequest('john@example.com', 'https://app.test/reset'));

        $this->assertTrue($response->sent);
    }

    private function user(): User
    {
        return User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'));
    }
}
