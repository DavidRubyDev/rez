<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\ListSessions;

interface ListSessionsUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(ListSessionsRequest $request): ListSessionsResponse;
}
