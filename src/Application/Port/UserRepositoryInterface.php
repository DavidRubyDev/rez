<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\User\User;
use Rez\Domain\User\UserCollection;
use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

interface UserRepositoryInterface
{
    /**
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findById(UserId $id): User;

    /**
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findByEmail(string $email): User;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function findAll(): UserCollection;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function findPage(
        ?string $search = null,
        ?UserRole $role = null,
        ?int $offset = null,
        ?int $limit = null,
        ?string $sortBy = null,
        ?string $sortDir = null,
    ): UserCollection;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function countPage(?string $search = null, ?UserRole $role = null): int;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(User $user): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function delete(UserId $id): void;
}
