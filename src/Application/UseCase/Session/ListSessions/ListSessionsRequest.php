<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\ListSessions;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class ListSessionsRequest
{
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly ?DateTimeImmutable $from = null,
        public readonly ?DateTimeImmutable $to = null,
    ) {
    }
}
