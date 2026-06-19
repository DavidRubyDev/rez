<?php

declare(strict_types=1);

namespace Rez\Domain\Resource;

final class ResourceCollection
{
    /** @param Resource[] $resources */
    private function __construct(
        private readonly array $resources = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /** @param Resource[] $resources */
    public static function fromArray(array $resources): self
    {
        return new self($resources);
    }

    public function add(Resource $resource): self
    {
        return new self([...$this->resources, $resource]);
    }

    public function isEmpty(): bool
    {
        return $this->resources === [];
    }

    public function count(): int
    {
        return count($this->resources);
    }

    /** @return Resource[] */
    public function toArray(): array
    {
        return $this->resources;
    }

    public function filter(callable $fn): self
    {
        return new self(array_values(array_filter($this->resources, $fn)));
    }

    public function findById(ResourceId $id): ?Resource
    {
        foreach ($this->resources as $resource) {
            if ($resource->id->equals($id)) {
                return $resource;
            }
        }

        return null;
    }
}
