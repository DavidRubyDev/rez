<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\GetResource;

interface GetResourceUseCaseInterface
{
    public function execute(GetResourceRequest $request): GetResourceResponse;
}
