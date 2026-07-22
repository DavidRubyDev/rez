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
                id                        CHAR(36)     NOT NULL PRIMARY KEY,
                type                      VARCHAR(100) NOT NULL,
                name                      VARCHAR(255) NOT NULL,
                capacity                  INT          NOT NULL,
                attributes                JSON         NOT NULL,
                active                    TINYINT(1)   NOT NULL DEFAULT 1,
                default_duration_minutes  INT          NULL
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
                external_ref VARCHAR(255) NULL,
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
                valid_from   DATE        NULL DEFAULT NULL,
                valid_until  DATE        NULL DEFAULT NULL,
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

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS newsletter_subscribers (
                id          CHAR(36)     NOT NULL PRIMARY KEY,
                email       VARCHAR(255) NOT NULL UNIQUE,
                name        VARCHAR(255) NULL,
                source      VARCHAR(20)  NOT NULL,
                opted_in_at DATETIME     NOT NULL
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS reservation_settings (
                id                               TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                auto_confirm                     TINYINT(1)       NOT NULL DEFAULT 0,
                auto_send_reservation_created    TINYINT(1)       NOT NULL DEFAULT 1,
                auto_send_reservation_confirmed  TINYINT(1)       NOT NULL DEFAULT 1,
                auto_send_reservation_cancelled  TINYINT(1)       NOT NULL DEFAULT 1,
                updated_at                       DATETIME         NOT NULL
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS mailer_settings (
                id           TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                from_address VARCHAR(255)     NOT NULL,
                from_name    VARCHAR(255)     NOT NULL,
                updated_at   DATETIME         NOT NULL
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS email_templates (
                id         CHAR(36)     NOT NULL PRIMARY KEY,
                subject    VARCHAR(255) NOT NULL,
                html       MEDIUMTEXT   NOT NULL,
                created_at DATETIME     NOT NULL
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS users (
                id                 CHAR(36)     NOT NULL PRIMARY KEY,
                name               VARCHAR(255) NOT NULL,
                email              VARCHAR(255) NOT NULL UNIQUE,
                password_hash      VARCHAR(255) NOT NULL,
                role               VARCHAR(20)  NOT NULL DEFAULT "customer",
                newsletter_opt_in  TINYINT(1)   NOT NULL DEFAULT 0,
                stripe_customer_id VARCHAR(255) NULL,
                created_at         DATETIME     NOT NULL
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                email      VARCHAR(255) NOT NULL PRIMARY KEY,
                token_hash CHAR(64)     NOT NULL,
                expires_at DATETIME     NOT NULL
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
        $pdo->exec('TRUNCATE TABLE newsletter_subscribers');
        $pdo->exec('TRUNCATE TABLE reservation_settings');
        $pdo->exec('TRUNCATE TABLE mailer_settings');
        $pdo->exec('TRUNCATE TABLE email_templates');
        $pdo->exec('TRUNCATE TABLE users');
        $pdo->exec('TRUNCATE TABLE password_reset_tokens');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // reservation_settings and mailer_settings are required singleton rows (id = 1) —
        // production seeding inserts them once via database/seeds/schema/. Restore both here
        // so every test starts from the same known-default row instead of a missing one.
        $pdo->exec("
            INSERT INTO reservation_settings
                (id, auto_confirm, auto_send_reservation_created, auto_send_reservation_confirmed, auto_send_reservation_cancelled, updated_at)
            VALUES
                (1, 0, 1, 1, 1, UTC_TIMESTAMP())
        ");

        $pdo->exec("
            INSERT INTO mailer_settings
                (id, from_address, from_name, updated_at)
            VALUES
                (1, 'noreply@example.com', 'Rez', UTC_TIMESTAMP())
        ");
    }
}
