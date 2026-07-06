<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\AdminUpdateUser;

interface AdminUpdateUserUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(AdminUpdateUserRequest $request): AdminUpdateUserResponse;
}
