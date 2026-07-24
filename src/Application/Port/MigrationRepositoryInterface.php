<?php

declare(strict_types=1);

namespace Rez\Application\Port;

interface MigrationRepositoryInterface
{
    /** @throws \Rez\Application\Exception\DatabaseException */
    public function ensureMigrationsTable(): void;

    /**
     * @return string[]
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function appliedMigrationNames(): array;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function applyMigration(string $name, string $sql): void;

    /** @throws \Rez\Application\Exception\DatabaseException */
    public function markMigrationApplied(string $name): void;

    /**
     * Serialises concurrent migration runs against the same database via a MySQL advisory
     * lock, so two containers starting at once can't both apply the same pending migration.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function withLock(callable $callback): mixed;
}
