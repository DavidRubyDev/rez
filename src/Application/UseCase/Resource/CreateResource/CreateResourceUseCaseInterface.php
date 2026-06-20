<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\CreateResource;

interface CreateResourceUseCaseInterface
{
    public function execute(CreateResourceRequest $request): CreateResourceResponse;
}
