<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\UpdateUser;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;

final class UpdateUserUseCase implements UpdateUserUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws DatabaseException
     */
    public function execute(UpdateUserRequest $request): UpdateUserResponse
    {
        try {
            $updated = $this->userRepository->findById($request->userId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load user.', 0, $e);
        }

        if ($request->name !== null) {
            $updated = $updated->withName($request->name);
        }

        if ($request->newsletterOptIn !== null) {
            $updated = $updated->withNewsletterOptIn($request->newsletterOptIn);
        }

        try {
            $this->userRepository->save($updated);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save user.', 0, $e);
        }

        return new UpdateUserResponse($updated);
    }
}
