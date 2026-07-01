<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Persistence\Mysql;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Infrastructure\Persistence\Mysql\MysqlDatabaseSeeder;

class MysqlDatabaseSeederLoggerTest extends TestCase
{
    public function testDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('exec')->willThrowException(new \PDOException('connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('critical')
            ->with(
                $this->stringContains('Database query failed'),
                $this->arrayHasKey('operation'),
            );

        $seeder = new MysqlDatabaseSeeder($pdo, $logger);

        $tmpFile = tempnam(sys_get_temp_dir(), 'rez_seed_') . '.sql';
        file_put_contents($tmpFile, 'INSERT INTO resources (id) VALUES (1)');

        try {
            $this->expectException(DatabaseException::class);
            $seeder->executeFile($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }
}
