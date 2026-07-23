<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\CancelSession;

interface CancelSessionUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\SessionNotFoundException
     * @throws \Rez\Domain\Exception\InvalidSessionStateException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(CancelSessionRequest $request): CancelSessionResponse;
}
