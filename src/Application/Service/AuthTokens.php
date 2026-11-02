<?php

declare(strict_types=1);

namespace Rez\Application\Service;

use DateTimeImmutable;

final class AuthTokens
{
    public function __construct(
        public readonly string $accessToken,
        public readonly DateTimeImmutable $accessTokenExpiresAt,
        public readonly string $refreshToken,
        public readonly DateTimeImmutable $refreshTokenExpiresAt,
    ) {
    }
}
