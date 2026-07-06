<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Login;

interface LoginUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\InvalidCredentialsException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(LoginRequest $request): LoginResponse;
}
