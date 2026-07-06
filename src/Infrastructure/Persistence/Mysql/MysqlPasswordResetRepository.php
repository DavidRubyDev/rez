<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use DateTimeImmutable;
use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\PasswordResetRepositoryInterface;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Shared\Utc;

final class MysqlPasswordResetRepository extends MysqlRepository implements PasswordResetRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws DatabaseException
     */
    public function save(string $email, string $tokenHash, DateTimeImmutable $expiresAt): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO password_reset_tokens (email, token_hash, expires_at)
                VALUES (:email, :token_hash, :expires_at)
                ON DUPLICATE KEY UPDATE
                    token_hash = VALUES(token_hash),
                    expires_at = VALUES(expires_at)
            ');

            $stmt->execute([
                ':email'      => $email,
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @return array{email: string, expires_at: DateTimeImmutable}
     * @throws InvalidTokenException
     * @throws DatabaseException
     */
    public function findByTokenHash(string $tokenHash): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM password_reset_tokens WHERE token_hash = :token_hash');
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
            'email'      => $this->str($row['email']),
            'expires_at' => new DateTimeImmutable($this->str($row['expires_at']), Utc::timezone()),
        ];
    }

    /**
     * @throws DatabaseException
     */
    public function deleteByEmail(string $email): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE email = :email');
            $stmt->execute([':email' => $email]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
