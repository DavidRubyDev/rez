<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\Register;

use Rez\Application\Service\AuthTokens;
use Rez\Domain\User\User;

final class RegisterResponse
{
    public function __construct(
        public readonly User $user,
        public readonly AuthTokens $tokens,
    ) {
    }
}
