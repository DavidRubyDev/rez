<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Unsubscribe;

interface UnsubscribeUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(UnsubscribeRequest $request): UnsubscribeResponse;
}
