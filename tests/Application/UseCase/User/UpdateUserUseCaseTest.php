<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\User;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\UseCase\User\UpdateUser\UpdateUserRequest;
use Rez\Application\UseCase\User\UpdateUser\UpdateUserUseCase;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;

class UpdateUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;
    private UpdateUserUseCase $useCase;
    private User $user;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->useCase    = new UpdateUserUseCase($this->repository);

        $this->user = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'), newsletterOptIn: false);
        $this->repository->method('findById')->willReturn($this->user);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->repository->method('findById')->willThrowException(new DatabaseException('pdo error'));
        $useCase = new UpdateUserUseCase($this->repository);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load user.');

        $useCase->execute(new UpdateUserRequest(UserId::generate(), 'New Name', null));
    }

    public function testNotFoundPropagates(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->repository->method('findById')->willThrowException(new UserNotFoundException('unknown'));
        $useCase = new UpdateUserUseCase($this->repository);

        $this->expectException(UserNotFoundException::class);

        $useCase->execute(new UpdateUserRequest(UserId::generate(), 'New Name', null));
    }

    public function testPartialUpdatePreservesUnchangedFields(): void
    {
        $response = $this->useCase->execute(new UpdateUserRequest($this->user->id, 'Jane Doe', null));

        $this->assertSame('Jane Doe', $response->user->name);
        $this->assertFalse($response->user->newsletterOptIn);
        $this->assertSame('john@example.com', $response->user->email);
    }

    public function testUpdatedFieldsReflectedInResponse(): void
    {
        $response = $this->useCase->execute(new UpdateUserRequest($this->user->id, 'Jane Doe', true));

        $this->assertSame('Jane Doe', $response->user->name);
        $this->assertTrue($response->user->newsletterOptIn);
    }

    public function testOriginalUserUnchangedByImmutability(): void
    {
        $this->useCase->execute(new UpdateUserRequest($this->user->id, 'Jane Doe', true));

        $this->assertSame('John Doe', $this->user->name);
        $this->assertFalse($this->user->newsletterOptIn);
    }

    public function testNoFieldsProvidedSavesUnchangedUser(): void
    {
        $response = $this->useCase->execute(new UpdateUserRequest($this->user->id, null, null));

        $this->assertSame('John Doe', $response->user->name);
        $this->assertFalse($response->user->newsletterOptIn);
    }
}
