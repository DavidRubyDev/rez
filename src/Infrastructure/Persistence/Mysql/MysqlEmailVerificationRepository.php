<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use DateTimeImmutable;
use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\EmailVerificationRepositoryInterface;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;
use Rez\Domain\User\UserId;

final class MysqlEmailVerificationRepository extends MysqlRepository implements EmailVerificationRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws DatabaseException
     */
    public function save(UserId $userId, string $tokenHash, DateTimeImmutable $issuedAt, DateTimeImmutable $expiresAt): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO email_verification_tokens (user_id, token_hash, issued_at, expires_at)
                VALUES (:user_id, :token_hash, :issued_at, :expires_at)
                ON DUPLICATE KEY UPDATE
                    token_hash = VALUES(token_hash),
                    issued_at  = VALUES(issued_at),
                    expires_at = VALUES(expires_at)
            ');

            $stmt->execute([
                ':user_id'    => $userId->toString(),
                ':token_hash' => $tokenHash,
                ':issued_at'  => $issuedAt->format('Y-m-d H:i:s'),
                ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @return array{user_id: UserId, expires_at: DateTimeImmutable}
     * @throws InvalidTokenException
     * @throws DatabaseException
     */
    public function findByTokenHash(string $tokenHash): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT user_id, expires_at FROM email_verification_tokens WHERE token_hash = :token_hash');
            $stmt->execute([':token_hash' => $tokenHash]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new InvalidTokenException('Token not found');
        }

        return [
            'user_id'    => UserId::fromString($this->str($row['user_id'])),
            'expires_at' => new DateTimeImmutable($this->str($row['expires_at']), Utc::timezone()),
        ];
    }

    /**
     * @throws DatabaseException
     */
    public function findIssuedAtForUser(UserId $userId): ?DateTimeImmutable
    {
        try {
            $stmt = $this->pdo->prepare('SELECT issued_at FROM email_verification_tokens WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $userId->toString()]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        $issuedAt = $stmt->fetchColumn();

        return $issuedAt === false ? null : new DateTimeImmutable($this->str($issuedAt), Utc::timezone());
    }

    /**
     * @throws DatabaseException
     */
    public function deleteForUser(UserId $userId): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM email_verification_tokens WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $userId->toString()]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
