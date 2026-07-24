<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use PDO;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MigrationRepositoryInterface;

final class MysqlMigrationRepository implements MigrationRepositoryInterface
{
    public const LOCK_NAME = 'rez_schema_migrations';
    private const LOCK_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly PDO $pdo,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function migrationsPath(): string
    {
        return dirname(__DIR__, 4) . '/database/migrations';
    }

    /** @throws DatabaseException */
    public function ensureMigrationsTable(): void
    {
        try {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    name       VARCHAR(255) NOT NULL,
                    applied_at DATETIME     NOT NULL,
                    PRIMARY KEY (name)
                )
            ');
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @return string[]
     * @throws DatabaseException
     */
    public function appliedMigrationNames(): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT name FROM schema_migrations');
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        /** @var string[] $names */
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $names;
    }

    /**
     * MySQL DDL statements implicitly commit — a migration containing CREATE TABLE/ALTER
     * TABLE can't be made atomic. If a statement fails partway through a multi-statement
     * migration, earlier statements in that file are already permanently applied even
     * though this migration is not recorded below, and the next run will retry the whole
     * file. Keep migrations small (one logical change per file) so that's easy to reason
     * about by hand if it ever happens.
     *
     * @throws DatabaseException
     */
    public function applyMigration(string $name, string $sql): void
    {
        try {
            foreach (SqlStatementSplitter::split($sql) as $statement) {
                $this->pdo->exec($statement);
            }

            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (name, applied_at) VALUES (:name, UTC_TIMESTAMP())');
            $stmt->execute([':name' => $name]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /** @throws DatabaseException */
    public function markMigrationApplied(string $name): void
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (name, applied_at) VALUES (:name, UTC_TIMESTAMP())');
            $stmt->execute([':name' => $name]);
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws DatabaseException
     */
    public function withLock(callable $callback): mixed
    {
        try {
            $stmt = $this->pdo->prepare('SELECT GET_LOCK(:name, :timeout)');
            $stmt->execute([':name' => self::LOCK_NAME, ':timeout' => self::LOCK_TIMEOUT_SECONDS]);
            $acquired = (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            $this->logger->critical('Database query failed', ['operation' => __METHOD__, 'error' => $e->getMessage()]);
            throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
        }

        if ($acquired !== 1) {
            throw new DatabaseException('Could not acquire the migration lock — another process may be migrating.');
        }

        try {
            return $callback();
        } finally {
            $this->pdo->prepare('SELECT RELEASE_LOCK(:name)')->execute([':name' => self::LOCK_NAME]);
        }
    }
}
