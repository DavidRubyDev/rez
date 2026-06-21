<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\DeleteResource;

interface DeleteResourceUseCaseInterface
{
    public function execute(DeleteResourceRequest $request): DeleteResourceResponse;
}
