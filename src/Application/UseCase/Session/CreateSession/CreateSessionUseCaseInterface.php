<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\CreateSession;

interface CreateSessionUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \InvalidArgumentException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(CreateSessionRequest $request): CreateSessionResponse;
}
