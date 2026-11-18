<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\ResendEmailVerification;

final class ResendEmailVerificationResponse
{
    public function __construct(
        public readonly bool $sent,
    ) {
    }
}
