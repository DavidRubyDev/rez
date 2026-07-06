<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\AdminUpdateUser;

use Rez\Domain\User\User;

final class AdminUpdateUserResponse
{
    public function __construct(
        public readonly User $user,
    ) {
    }
}
