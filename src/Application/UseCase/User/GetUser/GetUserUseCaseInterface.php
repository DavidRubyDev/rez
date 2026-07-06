<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\GetUser;

interface GetUserUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(GetUserRequest $request): GetUserResponse;
}
