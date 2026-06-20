<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\GetResource;

use Rez\Domain\Resource\Resource;

final class GetResourceResponse
{
    public function __construct(
        public readonly Resource $resource,
    ) {
    }
}
