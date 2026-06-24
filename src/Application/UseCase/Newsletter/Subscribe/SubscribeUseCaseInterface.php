<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Subscribe;

interface SubscribeUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(SubscribeRequest $request): SubscribeResponse;
}
