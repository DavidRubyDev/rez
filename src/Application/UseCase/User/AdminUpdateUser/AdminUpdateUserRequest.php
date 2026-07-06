<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\AdminUpdateUser;

use Rez\Domain\User\UserId;
use Rez\Domain\User\UserRole;

final class AdminUpdateUserRequest
{
    public function __construct(
        public readonly UserId $targetUserId,
        public readonly ?UserRole $role,
        public readonly ?bool $newsletterOptIn,
    ) {
    }
}
