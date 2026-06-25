<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Persistence\Mysql;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Infrastructure\Mapper\ResourceTypeMapper;
use Rez\Infrastructure\Persistence\Mysql\MysqlResourceRepository;

class MysqlRepositoryLoggerTest extends TestCase
{
    public function testDatabaseExceptionIsLoggedAsCritical(): void
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

        $repo = new MysqlResourceRepository($pdo, new ResourceTypeMapper(), $logger);

        $this->expectException(DatabaseException::class);
        $repo->findAll();
    }
}
