<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\DeleteUser;

interface DeleteUserUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws \Rez\Domain\Exception\CannotDeleteSelfException
     * @throws \Rez\Domain\Exception\CannotDeleteLastAdminException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(DeleteUserRequest $request): DeleteUserResponse;
}
