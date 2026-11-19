<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\VerifyEmail;

use Rez\Application\Service\AuthTokens;
use Rez\Domain\User\User;

final class VerifyEmailResponse
{
    public function __construct(
        public readonly User $user,
        public readonly AuthTokens $tokens,
    ) {
    }
}
