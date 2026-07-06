<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Register;

interface RegisterUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\EmailAlreadyRegisteredException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(RegisterRequest $request): RegisterResponse;
}
