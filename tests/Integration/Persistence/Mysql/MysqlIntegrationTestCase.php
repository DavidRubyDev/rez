<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use PDO;
use PHPUnit\Framework\TestCase;

abstract class MysqlIntegrationTestCase extends TestCase
{
    private static ?PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('DB_HOST') ?: '';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: '';
        $user = getenv('DB_USER') ?: '';
        $pass = getenv('DB_PASS') ?: '';

        if ($host === '' || $name === '' || $user === '') {
            self::$pdo = null;
            return;
        }

        self::$pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        self::createSchema(self::$pdo);
    }

    protected function setUp(): void
    {
        if (self::$pdo === null) {
            $this->markTestSkipped('No database connection configured. Set DB_HOST, DB_NAME, DB_USER, DB_PASS env vars.');
        }

        $this->truncateTables(self::$pdo);
    }

    protected function pdo(): PDO
    {
        return self::$pdo ?? throw new \RuntimeException('No PDO connection available.');
    }

    private static function createSchema(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS resources (
                id          CHAR(36)     NOT NULL PRIMARY KEY,
                type        VARCHAR(100) NOT NULL,
                name        VARCHAR(255) NOT NULL,
                capacity    INT          NOT NULL,
                attributes  JSON         NOT NULL
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS reservations (
                id           CHAR(36)     NOT NULL PRIMARY KEY,
                status       VARCHAR(20)  NOT NULL,
                start_at     DATETIME     NOT NULL,
                end_at       DATETIME     NOT NULL,
                party_name   VARCHAR(255) NOT NULL,
                party_email  VARCHAR(255) NOT NULL,
                party_size   INT          NOT NULL,
                party_phone  VARCHAR(50)  NULL,
                created_at   DATETIME     NOT NULL
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS reservation_resources (
                reservation_id CHAR(36) NOT NULL,
                resource_id    CHAR(36) NOT NULL,
                PRIMARY KEY (reservation_id, resource_id)
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS availability_rules (
                resource_id  CHAR(36)    NOT NULL,
                day_of_week  VARCHAR(10) NOT NULL,
                open_time    CHAR(5)     NOT NULL,
                close_time   CHAR(5)     NOT NULL,
                PRIMARY KEY (resource_id, day_of_week)
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS availability_overrides (
                resource_id  CHAR(36)   NOT NULL,
                date         DATE       NOT NULL,
                available    TINYINT(1) NOT NULL,
                PRIMARY KEY (resource_id, date)
            )
        ');
    }

    private function truncateTables(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('TRUNCATE TABLE reservation_resources');
        $pdo->exec('TRUNCATE TABLE reservations');
        $pdo->exec('TRUNCATE TABLE resources');
        $pdo->exec('TRUNCATE TABLE availability_rules');
        $pdo->exec('TRUNCATE TABLE availability_overrides');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
