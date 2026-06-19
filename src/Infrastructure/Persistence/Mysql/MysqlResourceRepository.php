<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use PDO;
use Rez\Application\Port\ResourceRepositoryInterface;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceCollection;
use Rez\Domain\Resource\ResourceId;
use Rez\Infrastructure\Mapper\ResourceTypeMapper;

final class MysqlResourceRepository extends MysqlRepository implements ResourceRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ResourceTypeMapper $typeMapper,
    ) {
    }

    public function findById(ResourceId $id): Resource
    {
        $stmt = $this->pdo->prepare('SELECT * FROM resources WHERE id = :id');
        $stmt->execute([':id' => $id->toString()]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new ResourceNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function findAll(): ResourceCollection
    {
        $stmt = $this->pdo->prepare('SELECT * FROM resources');
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ResourceCollection::fromArray(array_map($this->hydrate(...), $rows));
    }

    public function save(Resource $resource): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO resources (id, type, name, capacity, attributes)
            VALUES (:id, :type, :name, :capacity, :attributes)
            ON DUPLICATE KEY UPDATE
                type       = VALUES(type),
                name       = VALUES(name),
                capacity   = VALUES(capacity),
                attributes = VALUES(attributes)
        ');

        $stmt->execute([
            ':id'         => $resource->id->toString(),
            ':type'       => $this->typeMapper->toString($resource->type),
            ':name'       => $resource->name,
            ':capacity'   => $resource->capacity,
            ':attributes' => json_encode($resource->attributes),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Resource
    {
        /** @var array<string, mixed> $attributes */
        $attributes = json_decode($this->str($row['attributes']), true) ?? [];

        return new Resource(
            ResourceId::fromString($this->str($row['id'])),
            $this->typeMapper->fromString($this->str($row['type'])),
            $this->str($row['name']),
            $this->int($row['capacity']),
            $attributes,
        );
    }
}
