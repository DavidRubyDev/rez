<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\DeleteUser;

use Rez\Domain\User\UserId;

final class DeleteUserRequest
{
    public function __construct(
        public readonly UserId $actingUserId,
        public readonly UserId $targetUserId,
    ) {
    }
}
