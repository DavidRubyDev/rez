<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Persistence\Mysql;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Exception\DatabaseException;
use Rez\Domain\Reservation\ReservationSettings;
use Rez\Infrastructure\Persistence\Mysql\MysqlReservationSettingsRepository;

class MysqlReservationSettingsRepositoryLoggerTest extends TestCase
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

        $repo = new MysqlReservationSettingsRepository($pdo, $logger);

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

        $repo = new MysqlReservationSettingsRepository($pdo, $logger);

        $this->expectException(DatabaseException::class);
        $repo->update(new ReservationSettings(true, true, true, true));
    }
}
