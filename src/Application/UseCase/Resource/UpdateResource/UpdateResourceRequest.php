<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\UpdateResource;

use Rez\Domain\Resource\ResourceId;

final class UpdateResourceRequest
{
    /** @param array<string, mixed>|null $attributes */
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly ?string $name,
        public readonly ?int $capacity,
        public readonly ?array $attributes,
    ) {
    }
}
