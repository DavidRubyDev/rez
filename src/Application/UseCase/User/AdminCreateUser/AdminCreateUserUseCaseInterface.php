<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\AdminCreateUser;

interface AdminCreateUserUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\EmailAlreadyRegisteredException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(AdminCreateUserRequest $request): AdminCreateUserResponse;
}
