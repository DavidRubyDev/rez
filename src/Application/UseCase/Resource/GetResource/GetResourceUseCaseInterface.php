<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\GetResource;

interface GetResourceUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(GetResourceRequest $request): GetResourceResponse;
}
