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
use Rez\Domain\User\UserRole;

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
        $this->repository->method('findPage')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to list users.');

        $this->useCase->execute(new ListUsersRequest());
    }

    public function testReturnsUsersAndTotalFromRepository(): void
    {
        $user       = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'));
        $collection = UserCollection::fromArray([$user]);

        $this->repository->method('findPage')->willReturn($collection);
        $this->repository->method('countPage')->willReturn(1);

        $response = $this->useCase->execute(new ListUsersRequest());

        $this->assertSame($collection, $response->users);
        $this->assertSame(1, $response->total);
    }

    public function testPassesFiltersThroughToRepository(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findPage')
            ->with('jane', UserRole::Admin, null, null, null, null)
            ->willReturn(UserCollection::empty());

        $this->repository
            ->expects($this->once())
            ->method('countPage')
            ->with('jane', UserRole::Admin)
            ->willReturn(0);

        $this->useCase->execute(new ListUsersRequest(search: 'jane', role: UserRole::Admin));
    }

    public function testPassesPaginationAndSortThroughToRepository(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('findPage')
            ->with(null, null, 10, 20, 'name', 'desc')
            ->willReturn(UserCollection::empty());

        $this->repository->method('countPage')->willReturn(0);

        $this->useCase->execute(new ListUsersRequest(offset: 10, limit: 20, sortBy: 'name', sortDir: 'desc'));
    }

    public function testInvalidSortByThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListUsersRequest(sortBy: 'not_a_column'));
    }

    public function testInvalidLimitThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListUsersRequest(limit: 101));
    }

    public function testNegativeOffsetThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListUsersRequest(offset: -1));
    }

    public function testInvalidSortDirThrowsInvalidArgumentException(): void
    {
        $this->repository->expects($this->never())->method('findPage');

        $this->expectException(\InvalidArgumentException::class);
        $this->useCase->execute(new ListUsersRequest(sortDir: 'sideways'));
    }
}
