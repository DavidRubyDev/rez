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
    }
}
