<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use DateTimeImmutable;
use Rez\Domain\User\UserId;

interface EmailVerificationRepositoryInterface
{
    /**
     * A user has at most one outstanding link — saving replaces any earlier one.
     *
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function save(UserId $userId, string $tokenHash, DateTimeImmutable $issuedAt, DateTimeImmutable $expiresAt): void;

    /**
     * @return array{user_id: UserId, expires_at: DateTimeImmutable}
     * @throws \Rez\Domain\Exception\InvalidTokenException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findByTokenHash(string $tokenHash): array;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function findIssuedAtForUser(UserId $userId): ?DateTimeImmutable;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function deleteForUser(UserId $userId): void;
}
