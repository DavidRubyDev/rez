<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\UpdateUser;

interface UpdateUserUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(UpdateUserRequest $request): UpdateUserResponse;
}
