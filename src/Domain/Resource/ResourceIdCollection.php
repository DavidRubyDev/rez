<?php

declare(strict_types=1);

namespace Rez\Domain\Resource;

final class ResourceIdCollection
{
    /** @param ResourceId[] $ids */
    private function __construct(
        private readonly array $ids,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param ResourceId[] $ids
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $ids): self
    {
        if ($ids === []) {
            throw new \InvalidArgumentException('ResourceIdCollection must contain at least one ResourceId.');
        }

        return new self($ids);
    }

    public function add(ResourceId $id): self
    {
        return new self([...$this->ids, $id]);
    }

    public function contains(ResourceId $id): bool
    {
        foreach ($this->ids as $existing) {
            if ($existing->equals($id)) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    public function count(): int
    {
        return count($this->ids);
    }

    /** @return ResourceId[] */
    public function toArray(): array
    {
        return $this->ids;
    }
}
