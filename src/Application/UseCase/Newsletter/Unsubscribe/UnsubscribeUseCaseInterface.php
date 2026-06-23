<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Unsubscribe;

interface UnsubscribeUseCaseInterface
{
    public function execute(UnsubscribeRequest $request): UnsubscribeResponse;
}
