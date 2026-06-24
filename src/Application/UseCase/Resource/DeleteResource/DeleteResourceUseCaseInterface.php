<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\DeleteResource;

interface DeleteResourceUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(DeleteResourceRequest $request): DeleteResourceResponse;
}
