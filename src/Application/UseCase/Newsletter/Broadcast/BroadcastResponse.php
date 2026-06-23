<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Broadcast;

final class BroadcastResponse
{
    public function __construct(
        public readonly int $sent,
    ) {
    }
}
