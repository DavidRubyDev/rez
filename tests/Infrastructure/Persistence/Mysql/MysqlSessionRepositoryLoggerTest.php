<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Persistence\Mysql;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Session\Session;
use Rez\Domain\Session\SessionId;
use Rez\Infrastructure\Mapper\SessionStatusMapper;
use Rez\Infrastructure\Persistence\Mysql\MysqlSessionRepository;

class MysqlSessionRepositoryLoggerTest extends TestCase
{
    private function makeLoggerExpectingCritical(): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('critical')
            ->with(
                $this->stringContains('Database query failed'),
                $this->arrayHasKey('operation'),
            );

        return $logger;
    }

    public function testFindByIdDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $repo = new MysqlSessionRepository($pdo, new SessionStatusMapper(), $this->makeLoggerExpectingCritical());

        $this->expectException(DatabaseException::class);
        $repo->findById(SessionId::generate());
    }

    public function testFindForResourceDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $repo = new MysqlSessionRepository($pdo, new SessionStatusMapper(), $this->makeLoggerExpectingCritical());

        $this->expectException(DatabaseException::class);
        $repo->findForResource(ResourceId::generate());
    }

    public function testSaveDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $repo = new MysqlSessionRepository($pdo, new SessionStatusMapper(), $this->makeLoggerExpectingCritical());

        $session = Session::create(SessionId::generate(), ResourceId::generate(), new DateTimeImmutable('2024-06-03 09:00:00'), 60, 10);

        $this->expectException(DatabaseException::class);
        $repo->save($session);
    }
}
