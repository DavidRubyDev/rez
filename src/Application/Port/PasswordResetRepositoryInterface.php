<?php

declare(strict_types=1);

namespace Rez\Application\Port;

use DateTimeImmutable;

interface PasswordResetRepositoryInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function save(string $email, string $tokenHash, DateTimeImmutable $expiresAt): void;

    /**
     * @return array{email: string, expires_at: DateTimeImmutable}
     * @throws \Rez\Domain\Exception\InvalidTokenException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function findByTokenHash(string $tokenHash): array;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function deleteByEmail(string $email): void;
}
