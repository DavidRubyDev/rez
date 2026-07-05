<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\RequestPasswordReset;

final class RequestPasswordResetResponse
{
    public function __construct(
        public readonly bool $sent,
    ) {
    }
}
