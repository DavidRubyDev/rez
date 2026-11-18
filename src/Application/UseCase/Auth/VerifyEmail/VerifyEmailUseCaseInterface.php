<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\VerifyEmail;

interface VerifyEmailUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\InvalidTokenException
     * @throws \Rez\Domain\Exception\UserNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(VerifyEmailRequest $request): VerifyEmailResponse;
}
