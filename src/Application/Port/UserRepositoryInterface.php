<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\User\User;
use Rez\Domain\User\UserCollection;
use Rez\Domain\User\UserId;

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
    public function save(User $user): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function delete(UserId $id): void;
}
