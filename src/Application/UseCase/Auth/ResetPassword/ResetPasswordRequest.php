<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\ResetPassword;

final class ResetPasswordRequest
{
    public function __construct(
        public readonly string $token,
        public readonly string $newPassword,
    ) {
    }
}
