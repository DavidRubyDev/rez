<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\AdminCreateUser;

use Rez\Domain\User\User;

final class AdminCreateUserResponse
{
    public function __construct(
        public readonly User $user,
    ) {
    }
}
