<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use PDO;
use Psr\Log\NullLogger;
use Rez\Infrastructure\Persistence\Mysql\MysqlMigrationRepository;

class MysqlMigrationRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlMigrationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MysqlMigrationRepository($this->pdo(), new NullLogger());
        $this->repository->ensureMigrationsTable();
        $this->pdo()->exec('DELETE FROM schema_migrations');
        $this->pdo()->exec('DROP TABLE IF EXISTS migration_test_table');
    }

    public function testEnsureMigrationsTableIsIdempotent(): void
    {
        $this->repository->ensureMigrationsTable();
        $this->repository->ensureMigrationsTable();

        $this->assertSame([], $this->repository->appliedMigrationNames());
    }

    public function testAppliedMigrationNamesEmptyInitially(): void
    {
        $this->assertSame([], $this->repository->appliedMigrationNames());
    }

    public function testApplyMigrationExecutesSqlAndRecordsName(): void
    {
        $this->repository->applyMigration(
            '20260101000001_create_migration_test_table',
            'CREATE TABLE migration_test_table (id INT PRIMARY KEY)',
        );

        $this->assertContains('20260101000001_create_migration_test_table', $this->repository->appliedMigrationNames());

        $stmt = $this->pdo()->prepare("SHOW TABLES LIKE 'migration_test_table'");
        $stmt->execute();
        $this->assertNotFalse($stmt->fetch());
    }

    public function testApplyMigrationExecutesMultipleStatements(): void
    {
        $this->repository->applyMigration(
            '20260101000001_create_and_insert',
            "CREATE TABLE migration_test_table (id INT PRIMARY KEY);
             INSERT INTO migration_test_table (id) VALUES (1);
             INSERT INTO migration_test_table (id) VALUES (2)",
        );

        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM migration_test_table');
        $stmt->execute();
        $this->assertSame(2, (int) $stmt->fetchColumn());
    }

    public function testMarkMigrationAppliedDoesNotExecuteAnySql(): void
    {
        $this->repository->markMigrationApplied('20260101000001_never_run');

        $this->assertContains('20260101000001_never_run', $this->repository->appliedMigrationNames());

        $stmt = $this->pdo()->prepare("SHOW TABLES LIKE 'migration_test_table'");
        $stmt->execute();
        $this->assertFalse($stmt->fetch());
    }

    public function testWithLockReturnsCallbackValue(): void
    {
        $result = $this->repository->withLock(fn () => 'the-result');

        $this->assertSame('the-result', $result);
    }

    public function testWithLockReleasesLockAfterCallback(): void
    {
        $this->repository->withLock(fn () => null);

        $otherConnection = $this->secondConnection();
        $stmt = $otherConnection->prepare('SELECT GET_LOCK(?, 1)');
        $stmt->execute([MysqlMigrationRepository::LOCK_NAME]);

        $this->assertSame(1, (int) $stmt->fetchColumn());

        $otherConnection->prepare('SELECT RELEASE_LOCK(?)')->execute([MysqlMigrationRepository::LOCK_NAME]);
    }

    public function testWithLockBlocksConcurrentAcquisition(): void
    {
        $otherConnection = $this->secondConnection();

        $this->repository->withLock(function () use ($otherConnection): void {
            $stmt = $otherConnection->prepare('SELECT GET_LOCK(?, 1)');
            $stmt->execute([MysqlMigrationRepository::LOCK_NAME]);

            $this->assertSame(0, (int) $stmt->fetchColumn());
        });
    }

    private function secondConnection(): PDO
    {
        $host = getenv('DB_HOST') ?: '';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: '';
        $user = getenv('DB_USER') ?: '';
        $pass = getenv('DB_PASS') ?: '';

        return new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
