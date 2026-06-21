<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Seed\SeedDatabase;

final class SeedDatabaseResponse
{
    public function __construct(
        public readonly int $filesExecuted,
    ) {
    }
}
