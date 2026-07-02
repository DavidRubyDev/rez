<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Persistence\Mysql;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Domain\Mailer\MailerSettings;
use Rez\Infrastructure\Persistence\Mysql\MysqlMailerSettingsRepository;

class MysqlMailerSettingsRepositoryLoggerTest extends TestCase
{
    public function testGetDatabaseExceptionIsLoggedAsCritical(): void
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

        $repo = new MysqlMailerSettingsRepository($pdo, $logger);

        $this->expectException(DatabaseException::class);
        $repo->get();
    }

    public function testUpdateDatabaseExceptionIsLoggedAsCritical(): void
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

        $repo = new MysqlMailerSettingsRepository($pdo, $logger);

        $this->expectException(DatabaseException::class);
        $repo->update(new MailerSettings('info@studio.cz', 'Studio'));
    }
}
