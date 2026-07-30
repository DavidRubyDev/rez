<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionCollection;
use Rez\Domain\Session\SessionId;

interface SessionRepositoryInterface
{
    /**
     * @throws \Rez\Domain\Exception\SessionNotFoundException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findById(SessionId $id): Session;

    /**
     * Sessions belonging to a deactivated resource are never returned. $includeUnpublished lets
     * an authenticated admin see sessions on unpublished resources, mirroring the same flag on
     * ResourceRepositoryInterface::findPage().
     *
     * @param ResourceId[] $resourceIds an empty list means every resource, not none
     *
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findForResources(
        array $resourceIds = [],
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        bool $includeUnpublished = false,
    ): SessionCollection;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(Session $session): void;
}
