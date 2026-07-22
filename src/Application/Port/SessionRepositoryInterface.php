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

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function findForResource(ResourceId $resourceId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): SessionCollection;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(Session $session): void;
}
