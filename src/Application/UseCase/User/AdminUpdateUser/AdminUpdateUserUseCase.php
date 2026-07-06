<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\AdminUpdateUser;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;

final class AdminUpdateUserUseCase implements AdminUpdateUserUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws DatabaseException
     */
    public function execute(AdminUpdateUserRequest $request): AdminUpdateUserResponse
    {
        try {
            $updated = $this->userRepository->findById($request->targetUserId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load user.', 0, $e);
        }

        if ($request->role !== null) {
            $updated = $updated->withRole($request->role);
        }

        if ($request->newsletterOptIn !== null) {
            $updated = $updated->withNewsletterOptIn($request->newsletterOptIn);
        }

        try {
            $this->userRepository->save($updated);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save user.', 0, $e);
        }

        return new AdminUpdateUserResponse($updated);
    }
}
