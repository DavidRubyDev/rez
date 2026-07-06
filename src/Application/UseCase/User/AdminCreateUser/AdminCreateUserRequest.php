<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\AdminCreateUser;

use Rez\Domain\User\UserRole;

final class AdminCreateUserRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $resetBaseUrl,
        public readonly UserRole $role = UserRole::Customer,
        public readonly bool $newsletterOptIn = false,
    ) {
    }
}
