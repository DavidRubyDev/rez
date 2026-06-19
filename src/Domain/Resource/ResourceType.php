<?php

declare(strict_types=1);

namespace Rez\Domain\Resource;

final class ResourceType
{
    private function __construct(
        private readonly string $slug,
    ) {
    }

    public static function fromString(string $slug): self
    {
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid resource type slug.', $slug));
        }

        return new self($slug);
    }

    public function toString(): string
    {
        return $this->slug;
    }

    public function equals(self $other): bool
    {
        return $this->slug === $other->slug;
    }

    public function __toString(): string
    {
        return $this->slug;
    }
}
