<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\VerifyEmail;

final class VerifyEmailRequest
{
    public function __construct(
        public readonly string $token,
    ) {
    }
}
