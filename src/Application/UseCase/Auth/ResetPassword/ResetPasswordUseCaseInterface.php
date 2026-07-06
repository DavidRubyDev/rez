<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\ResetPassword;

interface ResetPasswordUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\InvalidTokenException
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(ResetPasswordRequest $request): ResetPasswordResponse;
}
