<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Unsubscribe;

final class UnsubscribeResponse
{
    public function __construct(
        public readonly bool $removed,
    ) {
    }
}
