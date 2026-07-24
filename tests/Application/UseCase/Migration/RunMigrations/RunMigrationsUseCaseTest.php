<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Migration\RunMigrations;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MigrationRepositoryInterface;
use Rez\Application\UseCase\Migration\RunMigrations\RunMigrationsRequest;
use Rez\Application\UseCase\Migration\RunMigrations\RunMigrationsUseCase;

class RunMigrationsUseCaseTest extends TestCase
{
    private MigrationRepositoryInterface&MockObject $repository;
    private RunMigrationsUseCase $useCase;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MigrationRepositoryInterface::class);
        $this->useCase     = new RunMigrationsUseCase($this->repository);
        $this->tempDir     = sys_get_temp_dir() . '/rez_run_migrations_' . uniqid();
        mkdir($this->tempDir);

        $this->repository
            ->method('withLock')
            ->willReturnCallback(fn (callable $callback) => $callback());
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function testEnsuresMigrationsTableBeforeReadingAppliedNames(): void
    {
        $this->repository->expects($this->once())->method('ensureMigrationsTable');
        $this->repository->method('appliedMigrationNames')->willReturn([]);

        $this->useCase->execute(new RunMigrationsRequest([$this->tempDir]));
    }

    public function testAppliesOnlyPendingMigrationsInOrder(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'CREATE TABLE a (id INT)');
        file_put_contents($this->tempDir . '/20260101000002_b.sql', 'CREATE TABLE b (id INT)');

        $this->repository->method('appliedMigrationNames')->willReturn(['20260101000001_a']);

        $applied = [];
        $this->repository
            ->expects($this->once())
            ->method('applyMigration')
            ->willReturnCallback(function (string $name, string $sql) use (&$applied): void {
                $applied[] = $name;
            });

        $this->useCase->execute(new RunMigrationsRequest([$this->tempDir]));

        $this->assertSame(['20260101000002_b'], $applied);
    }

    public function testResponseListsAppliedMigrationNames(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'CREATE TABLE a (id INT)');

        $this->repository->method('appliedMigrationNames')->willReturn([]);

        $response = $this->useCase->execute(new RunMigrationsRequest([$this->tempDir]));

        $this->assertSame(['20260101000001_a'], $response->applied);
    }

    public function testNoPendingMigrationsAppliesNothing(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'CREATE TABLE a (id INT)');

        $this->repository->method('appliedMigrationNames')->willReturn(['20260101000001_a']);
        $this->repository->expects($this->never())->method('applyMigration');

        $response = $this->useCase->execute(new RunMigrationsRequest([$this->tempDir]));

        $this->assertSame([], $response->applied);
    }

    public function testDatabaseExceptionFromApplyPropagates(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'CREATE TABLE a (id INT)');

        $this->repository->method('appliedMigrationNames')->willReturn([]);
        $this->repository->method('applyMigration')->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);

        $this->useCase->execute(new RunMigrationsRequest([$this->tempDir]));
    }
}
