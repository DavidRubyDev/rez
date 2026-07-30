<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rez\Application\UseCase\Migration\RunMigrations\RunMigrationsRequest;
use Rez\Application\UseCase\Migration\RunMigrations\RunMigrationsUseCase;
use Rez\Domain\Resource\ResourceId;
use Rez\Infrastructure\Persistence\Mysql\MysqlMigrationRepository;

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

    // resource_id in reservation_resources/availability_rules/availability_overrides/sessions
    // is a real foreign key against resources(id) — a bare ResourceId::generate() with no row
    // behind it only ever worked here because createSchema() used to hand-duplicate the schema
    // without the FK constraints production actually has. Insert a real row instead.
    protected function insertResource(?ResourceId $id = null, bool $published = true, bool $active = true): ResourceId
    {
        $id ??= ResourceId::generate();

        $stmt = $this->pdo()->prepare('
            INSERT INTO resources (id, type, name, capacity, attributes, published, active)
            VALUES (:id, :type, :name, :capacity, :attributes, :published, :active)
        ');
        $stmt->execute([
            ':id'         => $id->toString(),
            ':type'       => 'table',
            ':name'       => 'Test Resource',
            ':capacity'   => 10,
            ':attributes' => '{}',
            ':published'  => $published ? 1 : 0,
            ':active'     => $active ? 1 : 0,
        ]);

        return $id;
    }

    // Runs the real database/migrations/*.sql files through the real migration runner,
    // instead of a hand-duplicated copy of the schema — the two used to drift (see
    // docs/CONTEXT.md's published-flag follow-up fix), and this is the only way to
    // guarantee that never happens again: there is now exactly one schema definition.
    private static function createSchema(PDO $pdo): void
    {
        $repository = new MysqlMigrationRepository($pdo, new NullLogger());
        $useCase    = new RunMigrationsUseCase($repository);

        $useCase->execute(new RunMigrationsRequest([MysqlMigrationRepository::migrationsPath()]));
    }

    // schema_migrations is deliberately not truncated here — it's schema metadata, not
    // application data. Truncating it every test would make MysqlMigrationRepositoryTest
    // fight this method for control of its own state, and would force every subsequent
    // test to silently re-run migrations that already ran once in setUpBeforeClass().
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
        $pdo->exec('TRUNCATE TABLE sessions');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // reservation_settings and mailer_settings are required singleton rows (id = 1) —
        // the migrations insert them once via database/migrations/. Restore both here
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
