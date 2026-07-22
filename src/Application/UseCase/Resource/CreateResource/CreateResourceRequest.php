<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Resource\CreateResource;

final class CreateResourceRequest
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly int $capacity,
        public readonly array $attributes = [],
        public readonly ?int $defaultDurationMinutes = null,
    ) {
    }
}
