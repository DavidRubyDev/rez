<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class UsersConfig
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public readonly string $jwtSecret,
        public readonly string $cancellationSecret,
        public readonly int $accessTokenTtlSeconds = 900,
        public readonly int $passwordResetTtlMinutes = 60,
        public readonly int $refreshTokenTtlSeconds = 2592000,
        public readonly int $refreshTokenReuseGraceSeconds = 30,
        public readonly int $loginMaxFailedAttempts = 5,
        public readonly int $loginFailureWindowSeconds = 900,
        public readonly int $passwordMinLength = 15,
        public readonly int $passwordMaxLength = 128,
    ) {
        if ($jwtSecret === '') {
            throw new \InvalidArgumentException('jwtSecret must not be empty.');
        }

        if ($cancellationSecret === '') {
            throw new \InvalidArgumentException('cancellationSecret must not be empty.');
        }

        if ($accessTokenTtlSeconds < 1) {
            throw new \InvalidArgumentException("accessTokenTtlSeconds must be at least 1, got {$accessTokenTtlSeconds}.");
        }

        if ($passwordResetTtlMinutes < 1) {
            throw new \InvalidArgumentException("passwordResetTtlMinutes must be at least 1, got {$passwordResetTtlMinutes}.");
        }

        if ($refreshTokenTtlSeconds < 1) {
            throw new \InvalidArgumentException("refreshTokenTtlSeconds must be at least 1, got {$refreshTokenTtlSeconds}.");
        }

        if ($refreshTokenReuseGraceSeconds < 0) {
            throw new \InvalidArgumentException("refreshTokenReuseGraceSeconds must not be negative, got {$refreshTokenReuseGraceSeconds}.");
        }

        if ($loginMaxFailedAttempts < 1) {
            throw new \InvalidArgumentException("loginMaxFailedAttempts must be at least 1, got {$loginMaxFailedAttempts}.");
        }

        if ($loginFailureWindowSeconds < 1) {
            throw new \InvalidArgumentException("loginFailureWindowSeconds must be at least 1, got {$loginFailureWindowSeconds}.");
        }

        // NIST SP 800-63B's absolute floor — no deployment may go below it.
        if ($passwordMinLength < 8) {
            throw new \InvalidArgumentException("passwordMinLength must be at least 8, got {$passwordMinLength}.");
        }

        if ($passwordMaxLength < $passwordMinLength) {
            throw new \InvalidArgumentException("passwordMaxLength must be at least passwordMinLength ({$passwordMinLength}), got {$passwordMaxLength}.");
        }
    }
}
