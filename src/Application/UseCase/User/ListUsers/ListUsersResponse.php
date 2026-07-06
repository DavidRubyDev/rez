<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\ListUsers;

use Rez\Domain\User\UserCollection;

final class ListUsersResponse
{
    public function __construct(
        public readonly UserCollection $users,
    ) {
    }
}
