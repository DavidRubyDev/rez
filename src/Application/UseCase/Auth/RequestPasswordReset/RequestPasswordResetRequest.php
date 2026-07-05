<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\RequestPasswordReset;

final class RequestPasswordResetRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $resetBaseUrl,
    ) {
    }
}
