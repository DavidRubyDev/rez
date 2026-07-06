<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\GetUser;

use Rez\Domain\User\User;

final class GetUserResponse
{
    public function __construct(
        public readonly User $user,
    ) {
    }
}
