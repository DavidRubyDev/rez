<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\GetSession;

interface GetSessionUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\SessionNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(GetSessionRequest $request): GetSessionResponse;
}
