<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\RequestPasswordReset;

interface RequestPasswordResetUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(RequestPasswordResetRequest $request): RequestPasswordResetResponse;
}
