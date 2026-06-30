<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\ListSubscribers;

interface ListSubscribersUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(ListSubscribersRequest $request): ListSubscribersResponse;
}
