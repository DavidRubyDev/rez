<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Auth\ResendEmailVerification;

final class ResendEmailVerificationRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $verificationBaseUrl,
    ) {
    }
}
