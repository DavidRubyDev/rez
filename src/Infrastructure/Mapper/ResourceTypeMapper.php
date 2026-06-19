<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Mapper;

use Rez\Domain\Resource\ResourceType;

final class ResourceTypeMapper
{
    public function toString(ResourceType $type): string
    {
        return $type->toString();
    }

    public function fromString(string $value): ResourceType
    {
        return ResourceType::fromString($value);
    }
}
