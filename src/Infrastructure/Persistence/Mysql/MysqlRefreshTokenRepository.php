<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use DateTimeImmutable;
use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\RefreshTokenRepositoryInterface;
use Rez\Domain\Auth\RefreshToken;
use Rez\Domain\Auth\RefreshTokenFamilyId;
use Rez\Domain\Auth\RefreshTokenId;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\UserId;

final class MysqlRefreshTokenRepository extends MysqlRepository implements RefreshTokenRepositoryInterface
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly PDO $pdo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws DatabaseException
     */
    public function save(RefreshToken $token): void
    {
        $this->execute(
            '
            INSERT INTO refresh_tokens (id, family_id, user_id, token_hash, expires_at, created_at, used_at, revoked_at)
            VALUES (:id, :family_id, :user_id, :token_hash, :expires_at, :created_at, :used_at, :revoked_at)
            ON DUPLICATE KEY UPDATE
                used_at    = VALUES(used_at),
                revoked_at = VALUES(revoked_at)
            ',
            [
                ':id'         => $token->id->toString(),
                ':family_id'  => $token->familyId->toString(),
                ':user_id'    => $token->userId->toString(),
                ':token_hash' => $token->tokenHash,
                ':expires_at' => $token->expiresAt->format(self::DATETIME_FORMAT),
                ':created_at' => $token->createdAt->format(self::DATETIME_FORMAT),
                ':used_at'    => $token->usedAt?->format(self::DATETIME_FORMAT),
                ':revoked_at' => $token->revokedAt?->format(self::DATETIME_FORMAT),
            ],
            __METHOD__,
        );
    }

    /**
     * @throws InvalidTokenException
     * @throws DatabaseException
     */
    public function findByTokenHash(string $tokenHash): RefreshToken
    {
        $stmt = $this->execute(
            'SELECT * FROM refresh_tokens WHERE token_hash = :token_hash',
            [':token_hash' => $tokenHash],
            __METHOD__,
        );

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new InvalidTokenException('Refresh token not found.');
        }

        return RefreshToken::reconstruct(
            RefreshTokenId::fromString($this->str($row['id'])),
            RefreshTokenFamilyId::fromString($this->str($row['family_id'])),
            UserId::fromString($this->str($row['user_id'])),
            $this->str($row['token_hash']),
            $this->dateTime($this->str($row['expires_at'])),
            $this->dateTime($this->str($row['created_at'])),
            $this->nullDateTime($this->nullStr($row['used_at'])),
            $this->nullDateTime($this->nullStr($row['revoked_at'])),
        );
    }

    /**
     * @throws DatabaseException
     */
    public function revokeFamily(RefreshTokenFamilyId $familyId, DateTimeImmutable $at): void
    {
        $this->execute(
            'UPDATE refresh_tokens SET revoked_at = :at WHERE family_id = :family_id AND revoked_at IS NULL',
            [':at' => $at->format(self::DATETIME_FORMAT), ':family_id' => $familyId->toString()],
            __METHOD__,
        );
    }

    /**
     * @throws DatabaseException
     */
    public function revokeAllForUser(UserId $userId, DateTimeImmutable $at): void
    {
        $this->execute(
            'UPDATE refresh_tokens SET revoked_at = :at WHERE user_id = :user_id AND revoked_at IS NULL',
            [':at' => $at->format(self::DATETIME_FORMAT), ':user_id' => $userId->toString()],
            __METHOD__,
        );
    }

    /**
     * @param array<string, string|null> $params
     * @throws DatabaseException
     */
    private function execute(string $sql, array $params, string $operation): \PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => $operation, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return $stmt;
    }

    private function dateTime(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, Utc::timezone());
    }

    private function nullDateTime(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : $this->dateTime($value);
    }
}
