<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\User\UpdateUser;

use Rez\Domain\User\UserId;

final class UpdateUserRequest
{
    public function __construct(
        public readonly UserId $userId,
        public readonly ?string $name,
        public readonly ?bool $newsletterOptIn,
    ) {
    }
}
