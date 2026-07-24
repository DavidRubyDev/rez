<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Migration\BaselineMigrations;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\MigrationRepositoryInterface;
use Rez\Application\UseCase\Migration\BaselineMigrations\BaselineMigrationsRequest;
use Rez\Application\UseCase\Migration\BaselineMigrations\BaselineMigrationsUseCase;

class BaselineMigrationsUseCaseTest extends TestCase
{
    private MigrationRepositoryInterface&MockObject $repository;
    private BaselineMigrationsUseCase $useCase;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MigrationRepositoryInterface::class);
        $this->useCase     = new BaselineMigrationsUseCase($this->repository);
        $this->tempDir     = sys_get_temp_dir() . '/rez_baseline_migrations_' . uniqid();
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

    public function testMarksOnlyPendingMigrationsAsAppliedWithoutExecutingSql(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'CREATE TABLE a (id INT)');
        file_put_contents($this->tempDir . '/20260101000002_b.sql', 'CREATE TABLE b (id INT)');

        $this->repository->method('appliedMigrationNames')->willReturn(['20260101000001_a']);
        $this->repository->expects($this->never())->method('applyMigration');

        $marked = [];
        $this->repository
            ->expects($this->once())
            ->method('markMigrationApplied')
            ->willReturnCallback(function (string $name) use (&$marked): void {
                $marked[] = $name;
            });

        $this->useCase->execute(new BaselineMigrationsRequest([$this->tempDir]));

        $this->assertSame(['20260101000002_b'], $marked);
    }

    public function testResponseListsBaselinedMigrationNames(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'CREATE TABLE a (id INT)');

        $this->repository->method('appliedMigrationNames')->willReturn([]);

        $response = $this->useCase->execute(new BaselineMigrationsRequest([$this->tempDir]));

        $this->assertSame(['20260101000001_a'], $response->baselined);
    }

    public function testAlreadyBaselinedDatabaseMarksNothing(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'CREATE TABLE a (id INT)');

        $this->repository->method('appliedMigrationNames')->willReturn(['20260101000001_a']);
        $this->repository->expects($this->never())->method('markMigrationApplied');

        $response = $this->useCase->execute(new BaselineMigrationsRequest([$this->tempDir]));

        $this->assertSame([], $response->baselined);
    }
}
