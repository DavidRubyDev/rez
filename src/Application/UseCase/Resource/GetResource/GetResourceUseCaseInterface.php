<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\GetResource;

interface GetResourceUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     */
    public function execute(GetResourceRequest $request): GetResourceResponse;
}
