<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Migration\BaselineMigrations;

final class BaselineMigrationsResponse
{
    /** @param string[] $baselined */
    public function __construct(
        public readonly array $baselined,
    ) {
    }
}
