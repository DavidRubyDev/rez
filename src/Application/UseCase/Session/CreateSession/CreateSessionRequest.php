<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Session\CreateSession;

use Rez\Domain\Resource\ResourceId;

final class CreateSessionRequest
{
    public function __construct(
        public readonly ResourceId $resourceId,
        public readonly string $startTime,
        public readonly ?int $durationMinutes = null,
        public readonly ?int $capacity = null,
    ) {
    }
}
