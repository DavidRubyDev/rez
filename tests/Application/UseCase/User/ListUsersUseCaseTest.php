<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\User;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\UseCase\User\ListUsers\ListUsersRequest;
use Rez\Application\UseCase\User\ListUsers\ListUsersUseCase;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserCollection;
use Rez\Domain\User\UserId;

class ListUsersUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;
    private ListUsersUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->useCase    = new ListUsersUseCase($this->repository);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository->method('findAll')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to list users.');

        $this->useCase->execute(new ListUsersRequest());
    }

    public function testReturnsAllUsersFromRepository(): void
    {
        $user       = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'));
        $collection = UserCollection::fromArray([$user]);

        $this->repository->method('findAll')->willReturn($collection);

        $response = $this->useCase->execute(new ListUsersRequest());

        $this->assertSame($collection, $response->users);
    }
}
