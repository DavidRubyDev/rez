<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\ListUsers;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Application\Validation\ListParamsValidator;

final class ListUsersUseCase implements ListUsersUseCaseInterface
{
    private const SORTABLE = ['name', 'email', 'role', 'created_at'];

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @throws DatabaseException
     * @throws \InvalidArgumentException
     */
    public function execute(ListUsersRequest $request): ListUsersResponse
    {
        ListParamsValidator::validate($request->offset, $request->limit, $request->sortBy, $request->sortDir, self::SORTABLE);

        try {
            $users = $this->userRepository->findPage(
                search: $request->search,
                role: $request->role,
                offset: $request->offset,
                limit: $request->limit,
                sortBy: $request->sortBy,
                sortDir: $request->sortDir,
            );
            $total = $this->userRepository->countPage($request->search, $request->role);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to list users.', 0, $e);
        }

        return new ListUsersResponse($users, $total);
    }
}
