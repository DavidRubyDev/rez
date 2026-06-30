<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Broadcast;

final class BroadcastRequest
{
    public function __construct(
        public readonly string $resourceName,
        public readonly \DateTimeImmutable $resourceDate,
    ) {
    }
}
