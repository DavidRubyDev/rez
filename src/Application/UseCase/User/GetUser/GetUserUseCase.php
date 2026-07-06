<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\GetUser;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\UserRepositoryInterface;

final class GetUserUseCase implements GetUserUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws DatabaseException
     */
    public function execute(GetUserRequest $request): GetUserResponse
    {
        try {
            $user = $this->userRepository->findById($request->userId);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load user.', 0, $e);
        }

        return new GetUserResponse($user);
    }
}
