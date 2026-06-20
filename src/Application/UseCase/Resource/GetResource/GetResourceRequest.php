<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\GetResource;

use Rez\Domain\Resource\ResourceId;

final class GetResourceRequest
{
    public function __construct(
        public readonly ResourceId $resourceId,
    ) {
    }
}
