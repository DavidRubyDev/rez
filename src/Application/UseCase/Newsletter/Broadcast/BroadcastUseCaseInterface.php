<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Broadcast;

interface BroadcastUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(BroadcastRequest $request): BroadcastResponse;
}
