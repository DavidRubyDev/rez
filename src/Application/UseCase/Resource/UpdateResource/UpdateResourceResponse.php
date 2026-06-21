<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\UpdateResource;

use Rez\Domain\Resource\Resource;

final class UpdateResourceResponse
{
    public function __construct(
        public readonly Resource $resource,
    ) {
    }
}
