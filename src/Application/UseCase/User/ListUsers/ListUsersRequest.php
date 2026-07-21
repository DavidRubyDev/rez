<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\ListUsers;

use Rez\Domain\User\UserRole;

final class ListUsersRequest
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?UserRole $role = null,
        public readonly ?int $offset = null,
        public readonly ?int $limit = null,
        public readonly ?string $sortBy = null,
        public readonly ?string $sortDir = null,
    ) {
    }
}
