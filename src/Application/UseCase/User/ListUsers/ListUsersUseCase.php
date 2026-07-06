<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\ListUsers;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;

final class ListUsersUseCase implements ListUsersUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(ListUsersRequest $request): ListUsersResponse
    {
        try {
            $users = $this->userRepository->findAll();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to list users.', 0, $e);
        }

        return new ListUsersResponse($users);
    }
}
