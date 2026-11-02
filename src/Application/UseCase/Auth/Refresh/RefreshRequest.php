<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Refresh;

final class RefreshRequest
{
    public function __construct(
        public readonly string $refreshToken,
    ) {
    }
}
