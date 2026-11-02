<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Logout;

interface LogoutUseCaseInterface
{
    /**
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(LogoutRequest $request): LogoutResponse;
}
