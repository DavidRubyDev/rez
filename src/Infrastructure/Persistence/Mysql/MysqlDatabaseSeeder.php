<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use PDO;
use Rez\Application\Port\DatabaseSeederInterface;

final class MysqlDatabaseSeeder implements DatabaseSeederInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public static function seedsPath(): string
    {
        return dirname(__DIR__, 4) . '/database/seeds';
    }

    /**
     * @throws \RuntimeException
     */
    public function executeFile(string $filePath): void
    {
        $sql = file_get_contents($filePath);

        if ($sql === false) {
            throw new \RuntimeException("Cannot read seed file: {$filePath}");
        }

        foreach ($this->splitStatements($sql) as $statement) {
            $this->pdo->exec($statement);
        }
    }

    /** @return list<string> */
    private function splitStatements(string $sql): array
    {
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn (string $s) => $s !== '',
        );

        return array_values($statements);
    }
}
