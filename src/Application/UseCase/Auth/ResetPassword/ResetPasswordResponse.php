<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\ResetPassword;

final class ResetPasswordResponse
{
    public function __construct(
        public readonly bool $success,
    ) {
    }
}
