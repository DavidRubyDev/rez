<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\Unsubscribe;

final class UnsubscribeRequest
{
    public function __construct(
        public readonly string $email,
    ) {
    }
}
