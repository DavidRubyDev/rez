<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Register;

final class RegisterRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly bool $newsletterOptIn = false,
    ) {
    }
}
