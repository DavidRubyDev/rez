<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\CreateResource;

interface CreateResourceUseCaseInterface
{
    /**
     * @throws \InvalidArgumentException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(CreateResourceRequest $request): CreateResourceResponse;
}
