<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\User;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\UseCase\User\DeleteUser\DeleteUserRequest;
use Rez\Application\UseCase\User\DeleteUser\DeleteUserUseCase;
use Rez\Domain\Exception\CannotDeleteLastAdminException;
use Rez\Domain\Exception\CannotDeleteSelfException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

class DeleteUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;
    private DeleteUserUseCase $useCase;
    private UserId $actingUserId;

    protected function setUp(): void
    {
        $this->repository   = $this->createMock(UserRepositoryInterface::class);
        $this->useCase      = new DeleteUserUseCase($this->repository);
        $this->actingUserId = UserId::generate();
    }

    private function makeUser(UserId $id, UserRole $role = UserRole::Customer): User
    {
        return User::create($id, 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'), $role, false);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->repository->method('findById')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load user.');

        $this->useCase->execute(new DeleteUserRequest($this->actingUserId, UserId::generate()));
    }

    public function testNotFoundPropagates(): void
    {
        $this->repository->method('findById')->willThrowException(new UserNotFoundException('unknown'));

        $this->expectException(UserNotFoundException::class);

        $this->useCase->execute(new DeleteUserRequest($this->actingUserId, UserId::generate()));
    }

    public function testDeletingSelfThrowsCannotDeleteSelfException(): void
    {
        $this->repository->method('findById')->willReturn($this->makeUser($this->actingUserId, UserRole::Admin));
        $this->repository->expects($this->never())->method('delete');

        $this->expectException(CannotDeleteSelfException::class);

        $this->useCase->execute(new DeleteUserRequest($this->actingUserId, $this->actingUserId));
    }

    public function testDeletingTheLastAdminThrowsCannotDeleteLastAdminException(): void
    {
        $targetId = UserId::generate();
        $this->repository->method('findById')->willReturn($this->makeUser($targetId, UserRole::Admin));
        $this->repository->method('countPage')->with(null, UserRole::Admin)->willReturn(1);
        $this->repository->expects($this->never())->method('delete');

        $this->expectException(CannotDeleteLastAdminException::class);

        $this->useCase->execute(new DeleteUserRequest($this->actingUserId, $targetId));
    }

    public function testDeletingAnAdminWhenOtherAdminsExistSucceeds(): void
    {
        $targetId = UserId::generate();
        $this->repository->method('findById')->willReturn($this->makeUser($targetId, UserRole::Admin));
        $this->repository->method('countPage')->with(null, UserRole::Admin)->willReturn(2);
        $this->repository->expects($this->once())->method('delete')->with($targetId);

        $this->useCase->execute(new DeleteUserRequest($this->actingUserId, $targetId));
    }

    public function testDeletingACustomerSucceedsWithoutCheckingAdminCount(): void
    {
        $targetId = UserId::generate();
        $this->repository->method('findById')->willReturn($this->makeUser($targetId, UserRole::Customer));
        $this->repository->expects($this->never())->method('countPage');
        $this->repository->expects($this->once())->method('delete')->with($targetId);

        $this->useCase->execute(new DeleteUserRequest($this->actingUserId, $targetId));
    }

    public function testDeleteDatabaseExceptionPropagates(): void
    {
        $targetId = UserId::generate();
        $this->repository->method('findById')->willReturn($this->makeUser($targetId, UserRole::Customer));
        $this->repository->method('delete')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to delete user.');

        $this->useCase->execute(new DeleteUserRequest($this->actingUserId, $targetId));
    }
}
