<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Seed\SeedDatabase;

final class SeedDatabaseRequest
{
    public function __construct(
        public readonly string $seedsDirectory,
    ) {
    }
}
