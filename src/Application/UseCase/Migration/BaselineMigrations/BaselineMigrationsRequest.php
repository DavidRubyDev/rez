<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Migration\BaselineMigrations;

final class BaselineMigrationsRequest
{
    /** @param string[] $migrationsDirectories */
    public function __construct(
        public readonly array $migrationsDirectories,
    ) {
    }
}
