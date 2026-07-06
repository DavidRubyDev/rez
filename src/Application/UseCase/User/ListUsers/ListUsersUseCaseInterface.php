<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\ListUsers;

interface ListUsersUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(ListUsersRequest $request): ListUsersResponse;
}
