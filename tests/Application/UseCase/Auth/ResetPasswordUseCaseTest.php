<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\PasswordResetRepositoryInterface;
use Rez\Application\Port\UserRepositoryInterface;
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
    private ResetPasswordUseCase $useCase;
    private User $user;

    protected function setUp(): void
    {
        $this->userRepository          = $this->createMock(UserRepositoryInterface::class);
        $this->passwordResetRepository = $this->createMock(PasswordResetRepositoryInterface::class);
        $this->useCase                 = new ResetPasswordUseCase($this->userRepository, $this->passwordResetRepository);

        $this->user = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('old-password'));
    }

    private function record(\DateTimeImmutable $expiresAt): array
    {
        return ['email' => 'john@example.com', 'expires_at' => $expiresAt];
    }

    public function testUnknownTokenThrowsInvalidTokenException(): void
    {
        $this->passwordResetRepository->method('findByTokenHash')->willThrowException(new InvalidTokenException('Token not found'));

        $this->expectException(InvalidTokenException::class);

        $this->useCase->execute(new ResetPasswordRequest('bad-token', 'new-password'));
    }

    public function testFindByTokenHashDatabaseExceptionPropagates(): void
    {
        $this->passwordResetRepository->method('findByTokenHash')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password'));
    }

    public function testExpiredTokenThrowsInvalidTokenException(): void
    {
        $expired = Utc::now()->modify('-1 minute');
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record($expired));

        $this->expectException(InvalidTokenException::class);

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password'));
    }

    public function testSuccessSavesUserWithNewPasswordHash(): void
    {
        $valid = Utc::now()->modify('+10 minutes');
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record($valid));
        $this->userRepository->method('findByEmail')->willReturn($this->user);

        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (User $u) => $u->password->verify('new-password') && !$u->password->verify('old-password')));

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password'));
    }

    public function testSuccessDeletesTokenAfterUse(): void
    {
        $valid = Utc::now()->modify('+10 minutes');
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record($valid));
        $this->userRepository->method('findByEmail')->willReturn($this->user);

        $this->passwordResetRepository->expects($this->once())->method('deleteByEmail')->with('john@example.com');

        $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password'));
    }

    public function testSuccessReturnsSuccessTrue(): void
    {
        $valid = Utc::now()->modify('+10 minutes');
        $this->passwordResetRepository->method('findByTokenHash')->willReturn($this->record($valid));
        $this->userRepository->method('findByEmail')->willReturn($this->user);

        $response = $this->useCase->execute(new ResetPasswordRequest('some-token', 'new-password'));

        $this->assertTrue($response->success);
    }
}
