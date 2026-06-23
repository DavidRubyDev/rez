<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Seed;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Port\DatabaseSeederInterface;
use Rez\Application\UseCase\Seed\SeedDatabase\SeedDatabaseRequest;
use Rez\Application\UseCase\Seed\SeedDatabase\SeedDatabaseUseCase;

class SeedDatabaseUseCaseTest extends TestCase
{
    private DatabaseSeederInterface&MockObject $seeder;
    private SeedDatabaseUseCase $useCase;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->seeder  = $this->createMock(DatabaseSeederInterface::class);
        $this->useCase = new SeedDatabaseUseCase($this->seeder);
        $this->tempDir = sys_get_temp_dir() . '/rez_seed_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function testExecutesEachSqlFileViaSeeder(): void
    {
        file_put_contents($this->tempDir . '/001_a.sql', 'SELECT 1');
        file_put_contents($this->tempDir . '/002_b.sql', 'SELECT 2');

        $this->seeder->expects($this->exactly(2))->method('executeFile');

        $response = $this->useCase->execute(new SeedDatabaseRequest([$this->tempDir]));

        $this->assertSame(2, $response->filesExecuted);
    }

    public function testExecutesFilesInFilenameOrder(): void
    {
        file_put_contents($this->tempDir . '/003_c.sql', 'SELECT 3');
        file_put_contents($this->tempDir . '/001_a.sql', 'SELECT 1');
        file_put_contents($this->tempDir . '/002_b.sql', 'SELECT 2');

        $executedPaths = [];

        $this->seeder
            ->method('executeFile')
            ->willReturnCallback(function (string $path) use (&$executedPaths): void {
                $executedPaths[] = basename($path);
            });

        $this->useCase->execute(new SeedDatabaseRequest([$this->tempDir]));

        $this->assertSame(['001_a.sql', '002_b.sql', '003_c.sql'], $executedPaths);
    }

    public function testIgnoresNonSqlFiles(): void
    {
        file_put_contents($this->tempDir . '/001_a.sql', 'SELECT 1');
        file_put_contents($this->tempDir . '/README.md', 'ignore me');

        $this->seeder->expects($this->once())->method('executeFile');

        $response = $this->useCase->execute(new SeedDatabaseRequest([$this->tempDir]));

        $this->assertSame(1, $response->filesExecuted);
    }

    public function testEmptyDirectoryExecutesNothing(): void
    {
        $this->seeder->expects($this->never())->method('executeFile');

        $response = $this->useCase->execute(new SeedDatabaseRequest([$this->tempDir]));

        $this->assertSame(0, $response->filesExecuted);
    }

    public function testExecutesMultipleDirectoriesInOrder(): void
    {
        $dirA = sys_get_temp_dir() . '/rez_seed_a_' . uniqid();
        $dirB = sys_get_temp_dir() . '/rez_seed_b_' . uniqid();
        mkdir($dirA);
        mkdir($dirB);

        file_put_contents($dirA . '/001_a.sql', 'SELECT 1');
        file_put_contents($dirB . '/001_b.sql', 'SELECT 2');

        $executedPaths = [];

        $this->seeder
            ->method('executeFile')
            ->willReturnCallback(function (string $path) use (&$executedPaths): void {
                $executedPaths[] = basename($path);
            });

        $response = $this->useCase->execute(new SeedDatabaseRequest([$dirA, $dirB]));

        $this->assertSame(['001_a.sql', '001_b.sql'], $executedPaths);
        $this->assertSame(2, $response->filesExecuted);

        unlink($dirA . '/001_a.sql');
        rmdir($dirA);
        unlink($dirB . '/001_b.sql');
        rmdir($dirB);
    }

    public function testEmptyDirectoryInListIsSkipped(): void
    {
        $emptyDir = sys_get_temp_dir() . '/rez_seed_empty_' . uniqid();
        mkdir($emptyDir);

        file_put_contents($this->tempDir . '/001_a.sql', 'SELECT 1');

        $this->seeder->expects($this->once())->method('executeFile');

        $response = $this->useCase->execute(new SeedDatabaseRequest([$this->tempDir, $emptyDir]));

        $this->assertSame(1, $response->filesExecuted);

        rmdir($emptyDir);
    }
}
