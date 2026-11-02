<?php

declare(strict_types=1);

namespace Rez\Domain\Auth;

use DateTimeImmutable;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\UserId;

/**
 * One link in a rotation chain. Every refresh consumes the presented token and issues a
 * successor in the same family, so a family is one login on one device. Only the SHA-256
 * hash of the raw token is ever held — the raw value exists only in the client's hands.
 */
final class RefreshToken
{
    private function __construct(
        public readonly RefreshTokenId $id,
        public readonly RefreshTokenFamilyId $familyId,
        public readonly UserId $userId,
        public readonly string $tokenHash,
        public readonly DateTimeImmutable $expiresAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $usedAt,
        public readonly ?DateTimeImmutable $revokedAt,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function issue(
        RefreshTokenFamilyId $familyId,
        UserId $userId,
        string $rawToken,
        DateTimeImmutable $expiresAt,
    ): self {
        if ($rawToken === '') {
            throw new \InvalidArgumentException('Refresh token must not be empty.');
        }

        $now = Utc::now();

        if ($expiresAt <= $now) {
            throw new \InvalidArgumentException('Refresh token expiry must be in the future.');
        }

        return new self(RefreshTokenId::generate(), $familyId, $userId, self::hashOf($rawToken), $expiresAt, $now, null, null);
    }

    public static function reconstruct(
        RefreshTokenId $id,
        RefreshTokenFamilyId $familyId,
        UserId $userId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $usedAt,
        ?DateTimeImmutable $revokedAt,
    ): self {
        return new self($id, $familyId, $userId, $tokenHash, $expiresAt, $createdAt, $usedAt, $revokedAt);
    }

    public static function hashOf(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    /**
     * @throws InvalidTokenException
     */
    public function markUsed(DateTimeImmutable $at): self
    {
        if ($this->isUsed()) {
            throw new InvalidTokenException('Refresh token has already been used.');
        }

        return new self($this->id, $this->familyId, $this->userId, $this->tokenHash, $this->expiresAt, $this->createdAt, $at, $this->revokedAt);
    }

    public function wasUsedWithin(int $seconds, DateTimeImmutable $now): bool
    {
        return $this->usedAt !== null && $this->usedAt >= $now->modify("-{$seconds} seconds");
    }
}
