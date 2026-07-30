<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\ListSessions;

use DateTimeImmutable;
use Rez\Domain\Resource\ResourceId;

final class ListSessionsRequest
{
    /**
     * @param ResourceId[] $resourceIds an empty list means every resource the caller may see,
     *                                  rather than none
     */
    public function __construct(
        public readonly array $resourceIds = [],
        public readonly ?DateTimeImmutable $from = null,
        public readonly ?DateTimeImmutable $to = null,
        public readonly bool $includeUnpublished = false,
    ) {
    }
}
