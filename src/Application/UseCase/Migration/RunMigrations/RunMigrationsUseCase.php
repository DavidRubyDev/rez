<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Migration\RunMigrations;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Migration\MigrationFileResolver;
use Rez\Application\Port\MigrationRepositoryInterface;

final class RunMigrationsUseCase implements RunMigrationsUseCaseInterface
{
    public function __construct(
        private readonly MigrationRepositoryInterface $repository,
    ) {
    }

    /**
     * @throws \RuntimeException
     * @throws DatabaseException
     */
    public function execute(RunMigrationsRequest $request): RunMigrationsResponse
    {
        return $this->repository->withLock(function () use ($request): RunMigrationsResponse {
            $this->repository->ensureMigrationsTable();

            $pending = MigrationFileResolver::resolvePending(
                $request->migrationsDirectories,
                $this->repository->appliedMigrationNames(),
            );

            $applied = [];

            foreach ($pending as $name => $file) {
                $sql = file_get_contents($file);

                if ($sql === false) {
                    throw new \RuntimeException("Cannot read migration file: {$file}");
                }

                $this->repository->applyMigration($name, $sql);
                $applied[] = $name;
            }

            return new RunMigrationsResponse($applied);
        });
    }
}
