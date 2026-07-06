<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\UpdateUser;

use Rez\Domain\User\User;

final class UpdateUserResponse
{
    public function __construct(
        public readonly User $user,
    ) {
    }
}
