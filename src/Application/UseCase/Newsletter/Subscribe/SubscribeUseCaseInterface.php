<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Subscribe;

interface SubscribeUseCaseInterface
{
    public function execute(SubscribeRequest $request): SubscribeResponse;
}
