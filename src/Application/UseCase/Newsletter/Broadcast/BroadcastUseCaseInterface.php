<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Broadcast;

interface BroadcastUseCaseInterface
{
    public function execute(BroadcastRequest $request): BroadcastResponse;
}
