<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\User;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\UseCase\User\AdminUpdateUser\AdminUpdateUserRequest;
use Rez\Application\UseCase\User\AdminUpdateUser\AdminUpdateUserUseCase;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

class AdminUpdateUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;
    private AdminUpdateUserUseCase $useCase;
    private User $user;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->useCase    = new AdminUpdateUserUseCase($this->repository);

        $this->user = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'), UserRole::Customer, false);
        $this->repository->method('findById')->willReturn($this->user);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->repository->method('findById')->willThrowException(new DatabaseException('pdo error'));
        $useCase = new AdminUpdateUserUseCase($this->repository);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load user.');

        $useCase->execute(new AdminUpdateUserRequest(UserId::generate(), UserRole::Admin, null));
    }

    public function testNotFoundPropagates(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->repository->method('findById')->willThrowException(new UserNotFoundException('unknown'));
        $useCase = new AdminUpdateUserUseCase($this->repository);

        $this->expectException(UserNotFoundException::class);

        $useCase->execute(new AdminUpdateUserRequest(UserId::generate(), UserRole::Admin, null));
    }

    public function testRoleUpdatedCorrectly(): void
    {
        $response = $this->useCase->execute(new AdminUpdateUserRequest($this->user->id, UserRole::Admin, null));

        $this->assertSame(UserRole::Admin, $response->user->role);
    }

    public function testNewsletterUpdatedCorrectly(): void
    {
        $response = $this->useCase->execute(new AdminUpdateUserRequest($this->user->id, null, true));

        $this->assertTrue($response->user->newsletterOptIn);
    }

    public function testNoFieldsProvidedSavesUnchangedUser(): void
    {
        $response = $this->useCase->execute(new AdminUpdateUserRequest($this->user->id, null, null));

        $this->assertSame(UserRole::Customer, $response->user->role);
        $this->assertFalse($response->user->newsletterOptIn);
    }
}
