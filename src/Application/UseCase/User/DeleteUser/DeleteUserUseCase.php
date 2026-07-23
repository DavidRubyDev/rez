<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\DeleteUser;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;
use Rez\Domain\Exception\CannotDeleteLastAdminException;
use Rez\Domain\Exception\CannotDeleteSelfException;
use Rez\Domain\User\UserRole;

final class DeleteUserUseCase implements DeleteUserUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws CannotDeleteSelfException
     * @throws CannotDeleteLastAdminException
     * @throws DatabaseException
     */
    public function execute(DeleteUserRequest $request): DeleteUserResponse
    {
        try {
            $target = $this->userRepository->findById($request->targetUserId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load user.', 0, $e);
        }

        if ($request->actingUserId->equals($request->targetUserId)) {
            throw new CannotDeleteSelfException();
        }

        if ($target->isAdmin()) {
            try {
                $adminCount = $this->userRepository->countPage(null, UserRole::Admin);
            } catch (DatabaseException $e) {
                throw new DatabaseException('Failed to count admins.', 0, $e);
            }

            if ($adminCount <= 1) {
                throw new CannotDeleteLastAdminException();
            }
        }

        try {
            $this->userRepository->delete($request->targetUserId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to delete user.', 0, $e);
        }

        return new DeleteUserResponse();
    }
}
