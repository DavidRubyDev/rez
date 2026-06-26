<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Persistence\Mysql;

use PDO;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\DatabaseSeederInterface;

final class MysqlDatabaseSeeder implements DatabaseSeederInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public static function seedsPath(): string
    {
        return dirname(__DIR__, 4) . '/database/seeds/schema';
    }

    public static function dataPath(): string
    {
        return dirname(__DIR__, 4) . '/database/seeds/data';
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
            try {
                $this->pdo->exec($statement);
            } catch (\PDOException $e) {
                throw new DatabaseException($e->getMessage(), (int) $e->getCode(), $e);
            }
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
