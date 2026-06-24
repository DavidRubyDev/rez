<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\DeleteResource;

interface DeleteResourceUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     */
    public function execute(DeleteResourceRequest $request): DeleteResourceResponse;
}
