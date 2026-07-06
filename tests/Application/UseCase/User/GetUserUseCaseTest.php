<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\User;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\UseCase\User\GetUser\GetUserRequest;
use Rez\Application\UseCase\User\GetUser\GetUserUseCase;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;

class GetUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;
    private GetUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->useCase    = new GetUserUseCase($this->repository);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository->method('findById')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load user.');

        $this->useCase->execute(new GetUserRequest(UserId::generate()));
    }

    public function testThrowsWhenNotFound(): void
    {
        $this->repository->method('findById')->willThrowException(new UserNotFoundException('unknown'));

        $this->expectException(UserNotFoundException::class);

        $this->useCase->execute(new GetUserRequest(UserId::generate()));
    }

    public function testReturnsUserWhenFound(): void
    {
        $id   = UserId::generate();
        $user = User::create($id, 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'));

        $this->repository->method('findById')->willReturn($user);

        $response = $this->useCase->execute(new GetUserRequest($id));

        $this->assertSame($user, $response->user);
    }
}
