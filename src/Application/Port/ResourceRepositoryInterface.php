<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceCollection;
use Rez\Domain\Resource\ResourceId;

interface ResourceRepositoryInterface
{
    public function findById(ResourceId $id): Resource;

    public function findAll(): ResourceCollection;

    public function save(Resource $resource): void;
}
