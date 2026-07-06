<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Persistence\Mysql;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Domain\User\HashedPassword;
use Rez\Domain\User\User;
use Rez\Domain\User\UserId;
use Rez\Infrastructure\Mapper\UserRoleMapper;
use Rez\Infrastructure\Persistence\Mysql\MysqlUserRepository;

class MysqlUserRepositoryLoggerTest extends TestCase
{
    private function repository(PDO $pdo, LoggerInterface $logger): MysqlUserRepository
    {
        return new MysqlUserRepository($pdo, new UserRoleMapper(), $logger);
    }

    public function testFindByIdDatabaseExceptionIsLoggedAsCritical(): void
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
        $this->repository($pdo, $logger)->findById(UserId::generate());
    }

    public function testFindByEmailDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');

        $this->expectException(DatabaseException::class);
        $this->repository($pdo, $logger)->findByEmail('john@example.com');
    }

    public function testFindAllDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');

        $this->expectException(DatabaseException::class);
        $this->repository($pdo, $logger)->findAll();
    }

    public function testSaveDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');

        $user = User::create(UserId::generate(), 'John Doe', 'john@example.com', HashedPassword::fromPlainText('x'));

        $this->expectException(DatabaseException::class);
        $this->repository($pdo, $logger)->save($user);
    }

    public function testDeleteDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');

        $this->expectException(DatabaseException::class);
        $this->repository($pdo, $logger)->delete(UserId::generate());
    }
}
