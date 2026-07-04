<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Login;

final class LoginRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }
}
