<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceCollection;
use Rez\Domain\Resource\ResourceId;

interface ResourceRepositoryInterface
{
    /**
     * @throws \Rez\Domain\Exception\ResourceNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findById(ResourceId $id): Resource;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function findAll(): ResourceCollection;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(Resource $resource): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function delete(ResourceId $id): void;
}
