<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\ListResources;

final class ListResourcesRequest
{
    public function __construct(
        public readonly ?int $offset = null,
        public readonly ?int $limit = null,
        public readonly ?string $sortBy = null,
        public readonly ?string $sortDir = null,
        public readonly bool $includeUnpublished = false,
    ) {
    }
}
