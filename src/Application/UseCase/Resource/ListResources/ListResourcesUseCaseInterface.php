<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\ListResources;

interface ListResourcesUseCaseInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function execute(ListResourcesRequest $request): ListResourcesResponse;
}
