<?php

declare(strict_types=1);

namespace Rez\Domain\Resource;

final class Resource
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        private readonly ResourceId $id,
        private readonly ResourceType $type,
        private readonly string $name,
        private readonly int $capacity,
        private readonly array $attributes = [],
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Resource name must not be empty.');
        }

        if ($capacity < 1) {
            throw new \InvalidArgumentException(sprintf('Resource capacity must be at least 1, got %d.', $capacity));
        }
    }

    public function id(): ResourceId
    {
        return $this->id;
    }

    public function type(): ResourceType
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /** @param array<string, mixed> $attributes */
    public function withAttributes(array $attributes): self
    {
        return new self($this->id, $this->type, $this->name, $this->capacity, array_merge($this->attributes, $attributes));
    }
}
