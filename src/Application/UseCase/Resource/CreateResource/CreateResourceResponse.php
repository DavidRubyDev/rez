<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\CreateResource;

use Rez\Domain\Resource\Resource;

final class CreateResourceResponse
{
    public function __construct(
        public readonly Resource $resource,
    ) {
    }
}
