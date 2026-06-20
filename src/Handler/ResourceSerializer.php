<?php

declare(strict_types=1);

namespace Rez\Handler;

use Rez\Domain\Resource\Resource;
use Rez\Infrastructure\Mapper\ResourceTypeMapper;

final class ResourceSerializer
{
    /**
     * @return array{
     *     id: string,
     *     type: string,
     *     name: string,
     *     capacity: int,
     *     attributes: array<string, mixed>
     * }
     */
    public static function serialize(Resource $resource): array
    {
        $mapper = new ResourceTypeMapper();

        return [
            'id'         => $resource->id->toString(),
            'type'       => $mapper->toString($resource->type),
            'name'       => $resource->name,
            'capacity'   => $resource->capacity,
            'attributes' => $resource->attributes,
        ];
    }
}
