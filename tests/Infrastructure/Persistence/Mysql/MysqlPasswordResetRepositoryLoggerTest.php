<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Persistence\Mysql;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Domain\Shared\Utc;
use Rez\Infrastructure\Persistence\Mysql\MysqlPasswordResetRepository;

class MysqlPasswordResetRepositoryLoggerTest extends TestCase
{
    private function repository(PDO $pdo, LoggerInterface $logger): MysqlPasswordResetRepository
    {
        return new MysqlPasswordResetRepository($pdo, $logger);
    }

    public function testSaveDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('critical')
            ->with(
                $this->stringContains('Database query failed'),
                $this->arrayHasKey('operation'),
            );

        $this->expectException(DatabaseException::class);
        $this->repository($pdo, $logger)->save('john@example.com', 'hash', Utc::now());
    }

    public function testFindByTokenHashDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');

        $this->expectException(DatabaseException::class);
        $this->repository($pdo, $logger)->findByTokenHash('hash');
    }

    public function testDeleteByEmailDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');

        $this->expectException(DatabaseException::class);
        $this->repository($pdo, $logger)->deleteByEmail('john@example.com');
    }
}
