<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\ResendEmailVerification;

interface ResendEmailVerificationUseCaseInterface
{
    /**
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(ResendEmailVerificationRequest $request): ResendEmailVerificationResponse;
}
