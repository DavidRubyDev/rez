<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\UpdateResource;

interface UpdateResourceUseCaseInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     */
    public function execute(UpdateResourceRequest $request): UpdateResourceResponse;
}
