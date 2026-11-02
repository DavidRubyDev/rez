<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Application\UseCase\Auth\Logout\LogoutRequest;
use Rez\Application\UseCase\Auth\Logout\LogoutUseCase;
use Rez\Domain\Auth\RefreshToken;
use Rez\Domain\Auth\RefreshTokenFamilyId;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\UserId;

class LogoutUseCaseTest extends TestCase
{
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;
    private LogoutUseCase $useCase;

    protected function setUp(): void
    {
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->useCase                = new LogoutUseCase($this->refreshTokenRepository);
    }

    public function testRevokesTheFamilyOfThePresentedToken(): void
    {
        $familyId = RefreshTokenFamilyId::generate();
        $token    = RefreshToken::issue($familyId, UserId::generate(), 'presented-token', Utc::now()->modify('+1 day'));

        $this->refreshTokenRepository->method('findByTokenHash')->with(RefreshToken::hashOf('presented-token'))->willReturn($token);
        $this->refreshTokenRepository->expects($this->once())->method('revokeFamily')->with($this->callback(
            fn (RefreshTokenFamilyId $revoked): bool => $revoked->equals($familyId),
        ));

        $this->useCase->execute(new LogoutRequest('presented-token'));
    }

    public function testUnknownTokenIsANoOp(): void
    {
        $this->refreshTokenRepository->method('findByTokenHash')->willThrowException(new InvalidTokenException('Refresh token not found.'));
        $this->refreshTokenRepository->expects($this->never())->method('revokeFamily');

        $this->useCase->execute(new LogoutRequest('unknown-token'));
    }

    public function testFindDatabaseExceptionPropagates(): void
    {
        $this->refreshTokenRepository->method('findByTokenHash')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load refresh token.');

        $this->useCase->execute(new LogoutRequest('presented-token'));
    }

    public function testRevokeDatabaseExceptionPropagates(): void
    {
        $token = RefreshToken::issue(RefreshTokenFamilyId::generate(), UserId::generate(), 'presented-token', Utc::now()->modify('+1 day'));
        $this->refreshTokenRepository->method('findByTokenHash')->willReturn($token);
        $this->refreshTokenRepository->method('revokeFamily')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to revoke refresh token family.');

        $this->useCase->execute(new LogoutRequest('presented-token'));
    }
}
