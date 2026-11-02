<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use DateTimeImmutable;
use Rez\Domain\Auth\RefreshToken;
use Rez\Domain\Auth\RefreshTokenFamilyId;
use Rez\Domain\User\UserId;

interface RefreshTokenRepositoryInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(RefreshToken $token): void;

    /**
     * @throws \Rez\Domain\Exception\InvalidTokenException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findByTokenHash(string $tokenHash): RefreshToken;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function revokeFamily(RefreshTokenFamilyId $familyId, DateTimeImmutable $at): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function revokeAllForUser(UserId $userId, DateTimeImmutable $at): void;
}
