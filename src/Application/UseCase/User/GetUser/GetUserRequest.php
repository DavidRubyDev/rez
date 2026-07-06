<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\GetUser;

use Rez\Domain\User\UserId;

final class GetUserRequest
{
    public function __construct(
        public readonly UserId $userId,
    ) {
    }
}
