<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Persistence\Mysql;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Domain\Mailer\EmailTemplate;
use Rez\Domain\Mailer\EmailTemplateId;
use Rez\Infrastructure\Persistence\Mysql\MysqlEmailTemplateRepository;

class MysqlEmailTemplateRepositoryLoggerTest extends TestCase
{
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

        $repo = new MysqlEmailTemplateRepository($pdo, $logger);

        $this->expectException(DatabaseException::class);
        $repo->findById(EmailTemplateId::generate());
    }

    public function testFindAllDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');

        $repo = new MysqlEmailTemplateRepository($pdo, $logger);

        $this->expectException(DatabaseException::class);
        $repo->findAll();
    }

    public function testSaveDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');

        $repo = new MysqlEmailTemplateRepository($pdo, $logger);

        $this->expectException(DatabaseException::class);
        $repo->save(EmailTemplate::create(EmailTemplateId::generate(), 'Welcome', '<p>Hello</p>'));
    }

    public function testDeleteDatabaseExceptionIsLoggedAsCritical(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('connection lost'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');

        $repo = new MysqlEmailTemplateRepository($pdo, $logger);

        $this->expectException(DatabaseException::class);
        $repo->delete(EmailTemplateId::generate());
    }
}
