<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use PDO;
use Rez\Infrastructure\Persistence\Mysql\MysqlDatabaseSeeder;

class MysqlDatabaseSeederTest extends MysqlIntegrationTestCase
{
    private MysqlDatabaseSeeder $seeder;
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'rez_seed_') . '.sql';

        parent::setUp();

        $this->seeder = new MysqlDatabaseSeeder($this->pdo());
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testExecutesSingleStatementFromFile(): void
    {
        file_put_contents($this->tmpFile, "
            INSERT INTO resources (id, type, name, capacity, attributes)
            VALUES ('aaaaaaaa-0000-0000-0000-000000000001', 'table', 'Table 1', 4, '{}')
        ");

        $this->seeder->executeFile($this->tmpFile);

        $stmt = $this->pdo()->prepare("SELECT name FROM resources WHERE id = 'aaaaaaaa-0000-0000-0000-000000000001'");
        $stmt->execute();

        /** @var array{name: string}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame('Table 1', $row['name']);
    }

    public function testExecutesMultipleStatementsFromFile(): void
    {
        file_put_contents($this->tmpFile, "
            INSERT INTO resources (id, type, name, capacity, attributes)
            VALUES ('aaaaaaaa-0000-0000-0000-000000000001', 'table', 'Table 1', 4, '{}');
            INSERT INTO resources (id, type, name, capacity, attributes)
            VALUES ('aaaaaaaa-0000-0000-0000-000000000002', 'table', 'Table 2', 2, '{}')
        ");

        $this->seeder->executeFile($this->tmpFile);

        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM resources');
        $stmt->execute();

        /** @var int|false $count */
        $count = $stmt->fetchColumn();

        $this->assertSame(2, (int) $count);
    }

    public function testThrowsWhenFileDoesNotExist(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->seeder->executeFile('/tmp/rez_nonexistent_seed_file.sql');
    }
}
