<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Logout;

final class LogoutRequest
{
    public function __construct(
        public readonly string $refreshToken,
    ) {
    }
}
