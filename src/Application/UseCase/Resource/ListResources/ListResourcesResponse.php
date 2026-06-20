<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\ListResources;

use Rez\Domain\Resource\ResourceCollection;

final class ListResourcesResponse
{
    public function __construct(
        public readonly ResourceCollection $resources,
    ) {
    }
}
