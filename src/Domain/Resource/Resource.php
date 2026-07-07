<?php

declare(strict_types=1);

namespace Rez\Domain\Resource;

final class Resource
{
    /**
     * @param array<string, mixed> $attributes
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly ResourceId $id,
        public readonly ResourceType $type,
        public readonly string $name,
        public readonly int $capacity,
        public readonly array $attributes = [],
        public readonly bool $active = true,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Resource name must not be empty.');
        }

        if ($capacity < 1) {
            throw new \InvalidArgumentException(sprintf('Resource capacity must be at least 1, got %d.', $capacity));
        }
    }

    /** @param array<string, mixed> $attributes */
    public function withAttributes(array $attributes): self
    {
        return new self($this->id, $this->type, $this->name, $this->capacity, array_merge($this->attributes, $attributes), $this->active);
    }

    public function deactivate(): self
    {
        return new self($this->id, $this->type, $this->name, $this->capacity, $this->attributes, false);
    }
}
