<?php

declare(strict_types=1);

namespace Rez\Application\Config;

final class UsersConfig
{
    public function __construct(
        public readonly string $jwtSecret,
        public readonly int $jwtTtlSeconds = 3600,
        public readonly int $passwordResetTtlMinutes = 60,
    ) {
        if ($jwtSecret === '') {
            throw new \InvalidArgumentException('jwtSecret must not be empty.');
        }

        if ($jwtTtlSeconds < 1) {
            throw new \InvalidArgumentException("jwtTtlSeconds must be at least 1, got {$jwtTtlSeconds}.");
        }

        if ($passwordResetTtlMinutes < 1) {
            throw new \InvalidArgumentException("passwordResetTtlMinutes must be at least 1, got {$passwordResetTtlMinutes}.");
        }
    }
}
