<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\ListResources;

interface ListResourcesUseCaseInterface
{
    public function execute(ListResourcesRequest $request): ListResourcesResponse;
}
