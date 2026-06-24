<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Seed\SeedDatabase;

interface SeedDatabaseUseCaseInterface
{
    /**
     * @throws \RuntimeException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(SeedDatabaseRequest $request): SeedDatabaseResponse;
}
