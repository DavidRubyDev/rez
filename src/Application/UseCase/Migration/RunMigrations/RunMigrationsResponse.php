<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Migration\RunMigrations;

final class RunMigrationsResponse
{
    /** @param string[] $applied */
    public function __construct(
        public readonly array $applied,
    ) {
    }
}
