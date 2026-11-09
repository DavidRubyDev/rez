<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Login;

final class LoginRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        /** Where the attempt came from (e.g. the client IP) — failed logins are throttled per email and client. */
        public readonly string $clientKey,
    ) {
    }
}
