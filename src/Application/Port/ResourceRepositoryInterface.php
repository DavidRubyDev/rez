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

    /**
     * Returns active resources only. Deactivated resources are still reachable via findById()
     * (e.g. for historical reservation lookups) — they just never appear in listings.
     *
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findAll(): ResourceCollection;

    /**
     * $includeUnpublished lets an authenticated admin see unpublished resources in listings;
     * the public-facing default (false) excludes them, same asymmetry as $active but orthogonal
     * to it — a resource can be active and unpublished (visible to admins, hidden from the public
     * booking flow) independently of being active and deactivated.
     *
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findPage(
        ?int $offset = null,
        ?int $limit = null,
        ?string $sortBy = null,
        ?string $sortDir = null,
        bool $includeUnpublished = false,
    ): ResourceCollection;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function countPage(bool $includeUnpublished = false): int;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(Resource $resource): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function delete(ResourceId $id): void;
}
