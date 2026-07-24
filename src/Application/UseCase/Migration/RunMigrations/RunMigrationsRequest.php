<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Migration\RunMigrations;

final class RunMigrationsRequest
{
    /** @param string[] $migrationsDirectories */
    public function __construct(
        public readonly array $migrationsDirectories,
    ) {
    }
}
