<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\UpdateResource;

interface UpdateResourceUseCaseInterface
{
    public function execute(UpdateResourceRequest $request): UpdateResourceResponse;
}
