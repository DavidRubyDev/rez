<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\DeleteResource;

use Rez\Domain\Resource\ResourceId;

final class DeleteResourceRequest
{
    public function __construct(
        public readonly ResourceId $resourceId,
    ) {
    }
}
