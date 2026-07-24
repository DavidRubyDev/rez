<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Migration\RunMigrations;

interface RunMigrationsUseCaseInterface
{
    /**
     * @throws \RuntimeException
     * @throws \Rez\Application\Exception\DatabaseException
     */
    public function execute(RunMigrationsRequest $request): RunMigrationsResponse;
}
