<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Migration;

use PHPUnit\Framework\TestCase;
use Rez\Application\Migration\MigrationFileResolver;

class MigrationFileResolverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/rez_migrations_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function testReturnsAllFilesWhenNoneApplied(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'SELECT 1');
        file_put_contents($this->tempDir . '/20260101000002_b.sql', 'SELECT 2');

        $pending = MigrationFileResolver::resolvePending([$this->tempDir], []);

        $this->assertSame(['20260101000001_a', '20260101000002_b'], array_keys($pending));
    }

    public function testExcludesAlreadyAppliedNames(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'SELECT 1');
        file_put_contents($this->tempDir . '/20260101000002_b.sql', 'SELECT 2');

        $pending = MigrationFileResolver::resolvePending([$this->tempDir], ['20260101000001_a']);

        $this->assertSame(['20260101000002_b'], array_keys($pending));
    }

    public function testMapsNameToAbsoluteFilePath(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'SELECT 1');

        $pending = MigrationFileResolver::resolvePending([$this->tempDir], []);

        $this->assertSame($this->tempDir . '/20260101000001_a.sql', $pending['20260101000001_a']);
    }

    public function testSortsByNameAcrossMultipleDirectories(): void
    {
        $otherDir = sys_get_temp_dir() . '/rez_migrations_other_' . uniqid();
        mkdir($otherDir);

        file_put_contents($this->tempDir . '/20260101000003_c.sql', 'SELECT 3');
        file_put_contents($otherDir . '/20260101000001_a.sql', 'SELECT 1');
        file_put_contents($this->tempDir . '/20260101000002_b.sql', 'SELECT 2');

        $pending = MigrationFileResolver::resolvePending([$this->tempDir, $otherDir], []);

        $this->assertSame(
            ['20260101000001_a', '20260101000002_b', '20260101000003_c'],
            array_keys($pending),
        );

        unlink($otherDir . '/20260101000001_a.sql');
        rmdir($otherDir);
    }

    public function testIgnoresNonSqlFiles(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'SELECT 1');
        file_put_contents($this->tempDir . '/README.md', 'ignore me');

        $pending = MigrationFileResolver::resolvePending([$this->tempDir], []);

        $this->assertSame(['20260101000001_a'], array_keys($pending));
    }

    public function testEmptyDirectoryReturnsEmptyArray(): void
    {
        $pending = MigrationFileResolver::resolvePending([$this->tempDir], []);

        $this->assertSame([], $pending);
    }

    public function testAllAppliedReturnsEmptyArray(): void
    {
        file_put_contents($this->tempDir . '/20260101000001_a.sql', 'SELECT 1');

        $pending = MigrationFileResolver::resolvePending([$this->tempDir], ['20260101000001_a']);

        $this->assertSame([], $pending);
    }
}
